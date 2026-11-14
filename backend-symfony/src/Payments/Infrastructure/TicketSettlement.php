<?php

declare(strict_types=1);

namespace App\Payments\Infrastructure;

use App\Events\Application\Contract\TicketOrderSettlement;
use App\Payments\Application\Contract\PurposeSettlement;
use App\Payments\Domain\Charge;
use App\Payments\Domain\PaymentOrder;
use App\Payments\Domain\Refund;
use App\Payments\Domain\Repository\PaymentRepository;
use Psr\Clock\ClockInterface;

/** Ticket purpose: a verified charge becomes tickets in Events; money outside what Events can honour goes to review, never lost. */
final readonly class TicketSettlement implements PurposeSettlement
{
    public function __construct(private TicketOrderSettlement $tickets, private PaymentRepository $payments, private ClockInterface $clock)
    {
    }

    public function purpose(): string
    {
        return PaymentOrder::TICKET;
    }

    public function settle(PaymentOrder $order, Charge $charge): string
    {
        $outcome = $this->tickets->paid((string) $order->purposeRef, $charge->amountMinor);
        if (TicketOrderSettlement::ISSUED !== $outcome) {
            $this->payments->review($order->id, 'A ticket payment could not be honoured (hold lapsed, event cancelled or amount differs). Refund it in the Yoco dashboard.');
        }

        // The charge records its settlement id; a ticket payment is settled by its own charge.
        return $charge->id;
    }

    public function refund(PaymentOrder $order, Charge $charge, Refund $refund): void
    {
        $full = $refund->amountMinor + $this->payments->refunded($charge->id) >= $charge->amountMinor;
        if (TicketOrderSettlement::REVIEW === $this->tickets->refunded((string) $order->purposeRef, $full, $refund->amountMinor)) {
            $this->payments->audit($order->id, 'TICKET_REFUND_NEEDS_SELECTION', 'A partial ticket refund needs staff to choose which tickets it covers; the tickets stay valid until then.', null, $this->clock->now());
        }
    }

    public function release(PaymentOrder $order): void
    {
        $this->tickets->released((string) $order->purposeRef);
    }
}
