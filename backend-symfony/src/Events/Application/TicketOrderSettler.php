<?php

declare(strict_types=1);

namespace App\Events\Application;

use App\Events\Application\Contract\TicketOrderSettlement;
use App\Events\Domain\EventException;
use App\Events\Domain\Repository\TicketRepository;
use App\Events\Domain\TicketOrder;
use DateTimeImmutable;
use Psr\Clock\ClockInterface;

/**
 * Applies verified ticket payments. Runs inside Payments' posting transaction with the payments order already locked, then
 * locks the occurrence, then the ticket order. A late payment is retained and flagged for refund; it never confirms a place.
 */
final readonly class TicketOrderSettler implements TicketOrderSettlement
{
    public function __construct(private TicketRepository $tickets, private TicketIssuer $issuer, private ClockInterface $clock, private TicketEmails $emails, private TicketInvoices $invoices)
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
        $issued = $this->issuer->issue($order, $now);
        $this->invoices->issueFor($issued, $now);

        return self::ISSUED;
    }

    public function refunded(string $ticketOrderId, bool $full, int $amountMinor): string
    {
        $order = $this->lock($ticketOrderId);
        if ('REFUNDED' === $order->status) {
            return self::DONE;
        }
        $now = $this->clock->now();
        $rows = $this->tickets->ticketPrices($order->id);
        if ($full) {
            $uncredited = array_values(array_filter($rows, static fn (array $t): bool => null === $t['creditNoteId']));
            $this->tickets->voidAll($order->id);
            $this->tickets->setStatus($order->id, 'REFUNDED');
            $this->tickets->setUnmatchedRefund($order->id, 0);
            $this->credit($order, $amountMinor, true, $uncredited, $now);
            $this->tickets->audit($order->id, 'TICKET_ORDER_REFUNDED', 'Full refund recorded; every ticket is void.', $now);

            return self::DONE;
        }
        $pending = self::pending($rows);
        if ([] !== $pending && array_sum(array_column($pending, 'unitPriceMinor')) === $amountMinor) {
            $this->settlePartial($order, $pending, $amountMinor, $now);

            return self::DONE;
        }
        $this->tickets->setUnmatchedRefund($order->id, $this->tickets->unmatchedRefund($order->id) + $amountMinor);

        return self::REVIEW;
    }

    /**
     * Staff chose which tickets a partial refund covers (design 5.8): they are voided now, and when the voided, uncredited
     * tickets add up to the refund that is waiting, the refund is matched and credited. Returns whether it matched.
     *
     * @param list<string> $ticketIds
     */
    public function voided(string $ticketOrderId, array $ticketIds): bool
    {
        $order = $this->lock($ticketOrderId);
        if (!\in_array($order->status, ['PAID', 'PARTIALLY_REFUNDED'], true)) {
            throw EventException::conflict('Only tickets of a paid order can be voided.');
        }
        $now = $this->clock->now();
        $this->tickets->voidTickets($order->id, $ticketIds);
        $waiting = $this->tickets->unmatchedRefund($order->id);
        $pending = self::pending($this->tickets->ticketPrices($order->id));
        $matched = $waiting > 0 && [] !== $pending && array_sum(array_column($pending, 'unitPriceMinor')) === $waiting;
        if ($matched) {
            $this->tickets->setUnmatchedRefund($order->id, 0);
            $this->settlePartial($order, $pending, $waiting, $now);
        }

        return $matched;
    }

    /** @param list<array{id: string, typeName: string, unitPriceMinor: int}> $pending */
    private function settlePartial(TicketOrder $order, array $pending, int $amountMinor, DateTimeImmutable $now): void
    {
        $this->credit($order, $amountMinor, false, $pending, $now);
        $live = array_filter($this->tickets->ticketPrices($order->id), static fn (array $t): bool => 'VOID' !== $t['status']);
        $this->tickets->setStatus($order->id, [] === $live ? 'REFUNDED' : 'PARTIALLY_REFUNDED');
        $this->tickets->audit($order->id, 'TICKET_PARTIAL_REFUND', \count($pending).' ticket(s) void and credited for the refund.', $now);
    }

    /** @param list<array{id: string, typeName: string, unitPriceMinor: int}> $rows */
    private function credit(TicketOrder $order, int $amountMinor, bool $full, array $rows, DateTimeImmutable $now): void
    {
        $note = $this->invoices->creditFor($order, $amountMinor, $full, $rows, $now);
        if (null !== $note) {
            $this->tickets->creditTickets(array_column($rows, 'id'), $note);
        }
    }

    /**
     * @param list<array{id: string, lineId: string, typeName: string, unitPriceMinor: int, holder: string, status: string, creditNoteId: ?string}> $rows
     *
     * @return list<array{id: string, typeName: string, unitPriceMinor: int}> void tickets that no credit note covers yet
     */
    private static function pending(array $rows): array
    {
        $pending = [];
        foreach ($rows as $t) {
            if ('VOID' === $t['status'] && null === $t['creditNoteId']) {
                $pending[] = ['id' => $t['id'], 'typeName' => $t['typeName'], 'unitPriceMinor' => $t['unitPriceMinor']];
            }
        }

        return $pending;
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
