<?php

declare(strict_types=1);

namespace App\Payments\Application;

use App\Payments\Domain\Charge;
use App\Payments\Domain\PaymentException;
use App\Payments\Domain\PaymentOrder;
use App\Payments\Domain\Repository\PaymentRepository;
use App\Shared\Application\UnitOfWork;
use Exception;
use Psr\Clock\ClockInterface;
use Throwable;

/**
 * Posting to Giving, separate from durable verification (Java `PaymentPosting`). It runs in its own transaction and is
 * never called inside one: when the ledger transaction fails, a second transaction records that verified evidence still
 * needs posting, so it can be retried and no one collects the payment again. Simulation (test) charges never reach Giving.
 */
final readonly class PaymentPosting
{
    public const string NEEDS_POSTING = 'Verified financial evidence needs ledger posting. Use Retry Giving posting to retry; do not collect payment again.';

    public function __construct(
        private PaymentRepository $repository,
        private PurposeSettlements $settlements,
        private UnitOfWork $transactions,
        private ClockInterface $clock,
        private GuestConfirmations $guestConfirmations,
    ) {
    }

    /** @param (callable(): void)|null $authorize rechecked under the order lock */
    public function post(string $orderId, ?callable $authorize = null): void
    {
        $authorize ??= static function (): void {
        };
        try {
            $this->transactions->execute(function () use ($orderId, $authorize): void {
                $order = $this->repository->lock($orderId) ?? throw PaymentException::missing();
                $authorize();
                $this->postCharges($order);
                $this->applyRefunds($order);
                $this->settle($order);
            });
            $this->confirmGuest($orderId);
        } catch (Exception) {
            $this->transactions->execute(function () use ($orderId, $authorize): void {
                $this->repository->lock($orderId) ?? throw PaymentException::missing();
                $authorize();
                $this->repository->review($orderId, self::NEEDS_POSTING);
                $this->repository->audit($orderId, 'POSTING_REVIEW', 'Ledger posting failed; verified evidence retained.', null, $this->clock->now());
            });
        }
    }

    /**
     * A public giver's thank-you is queued only after the gift is committed, in its own transaction: a failure to queue it
     * must never undo or block the verified ledger posting. It is retried by the next posting attempt (idempotent per charge).
     */
    private function confirmGuest(string $orderId): void
    {
        try {
            $this->guestConfirmations->queueFor($orderId);
        } catch (Throwable) {
            try {
                $this->transactions->execute(function () use ($orderId): void {
                    $this->repository->audit($orderId, 'GUEST_CONFIRMATION_FAILED', 'The confirmation email could not be queued; posting again retries it.', null, $this->clock->now());
                });
            } catch (Throwable) {
                // Inside a caller's failed transaction the note cannot be written; the caller's own error surfaces.
            }
        }
    }

    public static function reference(PaymentOrder $order, string $reference): string
    {
        return $order->provider.':'.$order->mode.':'.$reference;
    }

    private function postCharges(PaymentOrder $order): void
    {
        if (!$order->isLive()) {
            $this->settlements->for($order)->release($order);

            return;
        }
        $settlement = $this->settlements->for($order);
        foreach ($this->repository->pendingCharges($order->id) as $charge) {
            if (null === $charge->giftId) {
                $gift = $settlement->settle($order, $charge);
                $this->repository->posted($charge->id, $gift);
            }
        }
    }

    private function applyRefunds(PaymentOrder $order): void
    {
        foreach ($this->repository->pendingRefunds($order->id) as $refund) {
            $matches = $this->repository->matchingCharges($order->id, $refund->chargeId);
            if (1 !== \count($matches)) {
                $this->repository->review($order->id, 'A verified refund needs an explicit matching charge.');
                continue;
            }
            $charge = $matches[0];
            if ($refund->amountMinor > $charge->amountMinor - $this->repository->refunded($charge->id)) {
                $this->repository->review($order->id, 'A refund exceeds the unrefunded charge. Review provider evidence.');
                continue;
            }
            if ($refund->refundedOn->isBefore($charge->receivedOn)) {
                $this->repository->review($order->id, 'Refund date precedes the payment date; review provider evidence.');
                continue;
            }
            if ($order->isLive()) {
                $this->settlements->for($order)->refund($order, $charge, $refund);
            }
            $this->repository->applied($refund->id, $charge->id);
            if ($order->isLive()) {
                $this->repository->review($order->id, 'Refund changed the net settlement. Review the bank match.');
            }
            $this->repository->audit($order->id, 'REFUND_APPLIED', $refund->reference, $refund->createdBy, $this->clock->now());
        }
    }

    /** Clears a posting review once nothing is pending, leaving settlement review where a charge is unmatched. */
    private function settle(PaymentOrder $order): void
    {
        $reason = $order->reviewReason;
        if (null === $reason
            || (!str_starts_with($reason, 'Verified financial evidence needs ledger posting.') && 'Verified financial evidence still needs matching or posting.' !== $reason)
            || [] !== $this->repository->pendingCharges($order->id) || [] !== $this->repository->pendingRefunds($order->id)) {
            return;
        }
        $this->repository->review($order->id, $this->unmatched($order->id) ? 'Awaiting settlement review.' : null);
    }

    /** Whether any charge of the order lacks a settlement review matching its current refunds. */
    public function unmatched(string $orderId): bool
    {
        return array_any($this->repository->charges($orderId), function (Charge $charge): bool {
            $review = $this->repository->latestReview($charge->id);

            return null === $review || !$review->matches($charge, $this->repository->refunded($charge->id));
        });
    }
}
