<?php

declare(strict_types=1);

namespace App\Events\Application;

use App\Events\Application\Contract\TicketOrderSettlement;
use App\Events\Domain\EventException;
use App\Events\Domain\Repository\TicketRepository;
use App\Events\Domain\TicketOrder;
use Psr\Clock\ClockInterface;

/**
 * Applies verified ticket payments. Runs inside Payments' posting transaction with the payments order already locked, then
 * locks the occurrence, then the ticket order. A late payment is retained and flagged for refund; it never confirms a place.
 */
final readonly class TicketOrderSettler implements TicketOrderSettlement
{
    public function __construct(private TicketRepository $tickets, private TicketIssuer $issuer, private ClockInterface $clock, private TicketEmails $emails)
    {
    }

    public function paid(string $ticketOrderId, int $amountMinor): string
    {
        $order = $this->lock($ticketOrderId);
        $now = $this->clock->now();
        if (\in_array($order->status, TicketOrder::HOLDING, true) || 'REFUNDED' === $order->status) {
            return self::ISSUED;
        }
        $occurrence = $this->tickets->lockOccurrence($order->occurrenceId);
        $honour = 'RESERVED' === $order->status && $amountMinor === $order->totalMinor && $order->expiresAt > $now && null !== $occurrence && $occurrence->status === 'SCHEDULED';
        if (!$honour) {
            if ('LATE_PAID_REVIEW' !== $order->status) {
                $this->tickets->setStatus($order->id, 'LATE_PAID_REVIEW');
                $this->tickets->audit($order->id, 'TICKET_PAYMENT_LATE', 'Payment arrived for an order that cannot be honoured; refund it.', $now);
            }

            return self::REVIEW;
        }
        $this->issuer->issue($order, $now);

        return self::ISSUED;
    }

    public function refunded(string $ticketOrderId, bool $full): string
    {
        $order = $this->lock($ticketOrderId);
        if (!$full) {
            return self::REVIEW;
        }
        if ('REFUNDED' !== $order->status) {
            $this->tickets->voidAll($order->id);
            $this->tickets->setStatus($order->id, 'REFUNDED');
            $this->tickets->audit($order->id, 'TICKET_ORDER_REFUNDED', 'Full refund recorded; every ticket is void.', $this->clock->now());
        }

        return self::DONE;
    }

    public function deliver(string $ticketOrderId): void
    {
        $this->emails->queueFor($ticketOrderId);
    }

    public function released(string $ticketOrderId): void
    {
        $order = $this->lock($ticketOrderId);
        if ('RESERVED' === $order->status) {
            $this->tickets->setStatus($order->id, 'RELEASED');
            $this->tickets->audit($order->id, 'TICKET_HOLD_RELEASED', 'Test-mode payment: hold released, nothing issued.', $this->clock->now());
        }
    }

    private function lock(string $ticketOrderId): TicketOrder
    {
        $found = $this->tickets->find($ticketOrderId) ?? throw EventException::missing('Ticket order');
        $this->tickets->lockOccurrence($found->occurrenceId);

        return $this->tickets->lock($ticketOrderId) ?? throw EventException::missing('Ticket order');
    }
}
