<?php

declare(strict_types=1);

namespace App\Events\Application;

use App\Events\Domain\Repository\TicketRepository;
use App\Events\Domain\TicketOrder;
use App\Payments\Application\Contract\GuestAccess;
use App\Shared\Application\IdGenerator;
use DateTimeImmutable;

/**
 * Turns a paid (or free) order into tickets, one per seat. A ticket's QR secret is derived from the server secret and the
 * ticket id, so the buyer's wallet can show it again while only its hash is stored.
 */
final readonly class TicketIssuer
{
    public function __construct(private TicketRepository $tickets, private GuestAccess $access, private IdGenerator $ids)
    {
    }

    /** Runs inside a transaction that holds the order lock. */
    public function issue(TicketOrder $order, DateTimeImmutable $at): TicketOrder
    {
        $issued = [];
        foreach ($order->lines as $line) {
            for ($i = 0; $i < $line->quantity; ++$i) {
                $id = $this->ids->uuid();
                $issued[] = ['id' => $id, 'lineId' => $line->id, 'codeHash' => $this->access->hash($this->secret($id)), 'holder' => ''];
            }
        }
        $this->tickets->issue($order->id, $issued);
        $this->tickets->setStatus($order->id, 'PAID', $at);
        $this->tickets->audit($order->id, 'TICKETS_ISSUED', \count($issued).' ticket(s) issued.', $at);

        return $this->tickets->find($order->id) ?? $order;
    }

    public function secret(string $ticketId): string
    {
        return $this->access->token('ticket-'.$ticketId);
    }

    /** The text in the ticket's QR code: `ticketId.secret`. */
    public function code(string $ticketId): string
    {
        return $ticketId.'.'.$this->secret($ticketId);
    }
}
