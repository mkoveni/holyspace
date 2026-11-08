<?php

declare(strict_types=1);

namespace App\Payments\Application\Command\RecordRefund;

use App\Payments\Application\PaymentAccess;
use App\Payments\Application\PaymentPosting;
use App\Payments\Domain\PaymentException;
use App\Payments\Domain\PaymentOrder;
use App\Payments\Domain\Refund;
use App\Payments\Domain\Repository\PaymentRepository;
use App\Shared\Application\ChurchCalendar;
use App\Shared\Application\IdGenerator;
use App\Shared\Application\UnitOfWork;
use Psr\Clock\ClockInterface;

/**
 * Records a provider-completed refund (Payfast) or matches an authenticated one (Yoco) to its charge, then posts it to
 * Giving in a separate transaction. Two transactions, so it is not on the command bus.
 */
final readonly class RecordRefundHandler
{
    public function __construct(
        private PaymentAccess $access,
        private PaymentRepository $repository,
        private UnitOfWork $transactions,
        private ClockInterface $clock,
        private ChurchCalendar $calendar,
        private IdGenerator $ids,
        private PaymentPosting $posting,
    ) {
    }

    public function handle(RecordRefundCommand $c): void
    {
        $this->access->refund($c->actorId);
        $reference = PaymentOrder::text($c->providerReference, 150, 'Provider refund reference');
        $reason = PaymentOrder::text($c->reason, 1000, 'Completed refund evidence');
        if ($c->amountMinor < 1 || $c->amountMinor > 100_000_000 || $c->refundedOn->isAfter($this->calendar->today())) {
            throw PaymentException::invalid('Enter the completed refund amount and date.');
        }
        $this->transactions->execute(function () use ($c, $reference, $reason): void {
            $order = $this->repository->lock($c->orderId) ?? throw PaymentException::missing();
            $this->access->refund($c->actorId);
            $charge = $this->repository->matchingCharges($order->id, $c->chargeId)[0] ?? throw PaymentException::missing();
            $prior = $this->repository->refund($order->provider, $order->mode, $reference);
            if (null !== $prior) {
                if ($prior->orderId !== $order->id || $prior->amountMinor !== $c->amountMinor || !$prior->refundedOn->equals($c->refundedOn)
                    || (null !== $prior->chargeId && $prior->chargeId !== $charge->id)) {
                    throw PaymentException::conflict('Refund reference conflicts with existing evidence.');
                }
                if (null === $prior->chargeId) {
                    $this->repository->matchRefund($prior->id, $charge->id);
                }

                return;
            }
            if ('yoco' === $order->provider) {
                throw PaymentException::conflict('Await the authenticated Yoco refund notification before matching it.');
            }
            if ($c->refundedOn->isBefore($charge->receivedOn) || $c->amountMinor > $charge->amountMinor - $this->repository->refunded($charge->id)) {
                throw PaymentException::conflict('Refund exceeds the remaining charge or precedes its payment date.');
            }
            $now = $this->clock->now();
            $refund = new Refund(
                $this->ids->uuid(),
                $order->id,
                $charge->id,
                $order->provider,
                $order->mode,
                $reference,
                $c->amountMinor,
                $c->refundedOn,
                false,
                $reason,
                $c->actorId,
                $now
            );
            if (!$this->repository->insertRefund($refund)) {
                throw PaymentException::conflict('Refund reference was recorded concurrently. Refresh before retrying.');
            }
            $this->repository->audit($order->id, 'REFUND_CONFIRMED', $reference.': '.$reason, $c->actorId, $now);
        });
        $this->posting->post($c->orderId, fn () => $this->access->refund($c->actorId));
    }
}
