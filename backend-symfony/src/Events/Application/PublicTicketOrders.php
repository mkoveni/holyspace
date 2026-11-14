<?php

declare(strict_types=1);

namespace App\Events\Application;

use App\Events\Application\Contract\TicketCatalog;
use App\Events\Domain\EventException;
use App\Events\Domain\Repository\TicketRepository;
use App\Events\Domain\TicketFeeSettings;
use App\Events\Domain\TicketOrder;
use App\Payments\Application\Contract\GuestAccess;
use DateTimeImmutable;

/** What a buyer sees of their own order. A wrong token and an unknown order are the same "not found". */
final readonly class PublicTicketOrders
{
    public function __construct(private TicketRepository $tickets, private TicketIssuer $issuer, private GuestAccess $access, private TicketCatalog $catalog)
    {
    }

    public function fees(): TicketFeeSettings
    {
        return $this->tickets->fees();
    }

    /** @return array{id: string, state: string, totalMinor: int, seats: int, walletToken: ?string} */
    public function status(string $accessHash): array
    {
        $order = $this->tickets->byAccess($accessHash) ?? throw EventException::missing('Ticket order');
        $state = self::state($order);

        return [
            'id' => $order->id,
            'state' => $state,
            'totalMinor' => $order->totalMinor,
            'seats' => $order->seats(),
            'walletToken' => 'PAID' === $state ? $this->access->token('wallet-'.$order->id) : null,
        ];
    }

    /**
     * @return array{orderId: string, state: string, title: string, startsAt: DateTimeImmutable, endsAt: DateTimeImmutable, timeZone: string, venueName: string, venueAddress: string, subtotalMinor: int, feeMinor: int, totalMinor: int, tickets: list<array{id: string, type: string, holder: string, status: string, code: ?string}>}
     */
    public function wallet(string $walletHash): array
    {
        $order = $this->tickets->byWallet($walletHash) ?? throw EventException::missing('Ticket order');
        $event = $this->catalog->event($order->occurrenceId) ?? throw EventException::missing('Event');
        $tickets = [];
        foreach ($this->tickets->tickets($order->id) as $t) {
            $tickets[] = ['id' => $t['id'], 'type' => $t['typeName'], 'holder' => $t['holder'], 'status' => $t['status'], 'code' => 'VOID' === $t['status'] ? null : $this->issuer->code($t['id'])];
        }

        return [
            'orderId' => $order->id,
            'state' => self::state($order),
            'title' => $event['title'],
            'startsAt' => $event['startsAt'],
            'endsAt' => $event['endsAt'],
            'timeZone' => $event['timeZone'],
            'venueName' => $event['venueName'],
            'venueAddress' => $event['venueAddress'],
            'subtotalMinor' => $order->subtotalMinor,
            'feeMinor' => $order->feeMinor,
            'totalMinor' => $order->totalMinor,
            'tickets' => $tickets,
        ];
    }

    private static function state(TicketOrder $order): string
    {
        return match ($order->status) {
            'RESERVED' => 'AWAITING_PAYMENT',
            'PAID', 'PARTIALLY_REFUNDED' => 'PAID',
            'EXPIRED' => 'EXPIRED',
            'RELEASED' => 'TEST_PAYMENT',
            'REFUNDED' => 'REFUNDED',
            default => 'IN_REVIEW',
        };
    }
}
