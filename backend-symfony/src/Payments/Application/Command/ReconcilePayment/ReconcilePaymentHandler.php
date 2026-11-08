<?php

declare(strict_types=1);

namespace App\Payments\Application\Command\ReconcilePayment;

use App\Payments\Application\PaymentAccess;
use App\Payments\Application\PaymentHash;
use App\Payments\Application\PaymentPosting;
use App\Payments\Domain\PaymentException;
use App\Payments\Domain\PaymentOrder;
use App\Payments\Domain\Repository\PaymentRepository;
use App\Payments\Domain\SettlementReview;
use App\Shared\Application\UnitOfWork;
use Psr\Clock\ClockInterface;

/**
 * Records an immutable, versioned bank settlement review of a live charge, retrying any pending ledger posting first
 * (in its own transaction). Idempotent on the request id. Not on the command bus: posting runs separately.
 */
final readonly class ReconcilePaymentHandler
{
    public function __construct(
        private PaymentAccess $access,
        private PaymentRepository $repository,
        private UnitOfWork $transactions,
        private ClockInterface $clock,
        private PaymentPosting $posting,
    ) {
    }

    public function handle(ReconcilePaymentCommand $c): string
    {
        $this->access->reconcile($c->actorId);
        $order = $this->repository->find($c->orderId) ?? throw PaymentException::missing();
        if (!$order->isLive()) {
            throw PaymentException::conflict('Simulation payments have no real bank settlement to review.');
        }
        $this->posting->post($c->orderId, fn () => $this->access->reconcile($c->actorId));
        $reference = PaymentOrder::text($c->settlementReference, 200, 'Settlement reference');
        $note = PaymentOrder::text($c->note, 1000, 'Review evidence');
        $hash = PaymentHash::of(
            'settlement',
            $c->actorId,
            $c->orderId,
            $c->chargeId,
            $c->expectedVersion,
            $reference,
            $c->feeMinor,
            $c->bankMinor,
            $note
        );

        return $this->transactions->execute(function () use ($c, $reference, $note, $hash): string {
            $order = $this->repository->lock($c->orderId) ?? throw PaymentException::missing();
            $this->access->reconcile($c->actorId);
            $replay = $this->repository->reviewRequest($c->requestId);
            if (null !== $replay) {
                if ($replay->requestHash !== $hash) {
                    throw PaymentException::conflict('Review request identifier was already used.');
                }

                return $replay->id;
            }
            $charge = $this->repository->matchingCharges($order->id, $c->chargeId)[0] ?? throw PaymentException::missing();
            $version = $this->repository->latestReview($charge->id)->version ?? 0;
            if ($version !== $c->expectedVersion) {
                throw PaymentException::conflict('Settlement review changed. Refresh before trying again.');
            }
            $now = $this->clock->now();
            $review = new SettlementReview(
                $c->requestId,
                $order->id,
                $charge->id,
                $version + 1,
                $reference,
                $c->feeMinor,
                $c->bankMinor,
                $this->repository->refunded($charge->id),
                $note,
                $c->actorId,
                $now,
                $hash
            );
            $this->repository->insertReview($review);
            $pending = [] !== $this->repository->pendingRefunds($order->id)
                || ($order->isLive() && [] !== $this->repository->pendingCharges($order->id));
            $this->repository->review($order->id, $pending ? 'Verified financial evidence still needs matching or posting.'
                : ($this->posting->unmatched($order->id) ? 'Bank amount does not match the charge less refunds and fees.' : null));
            $this->repository->audit($order->id, 'SETTLEMENT_REVIEWED', $note, $c->actorId, $now);

            return $review->id;
        });
    }
}
