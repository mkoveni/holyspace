<?php

declare(strict_types=1);

namespace App\Events\Application;

use App\Events\Domain\EventException;
use App\Events\Domain\Repository\TicketRepository;
use App\Events\Domain\TicketOrder;
use App\Events\Domain\TicketOrderLine;
use App\Events\Domain\TicketType;
use App\Payments\Application\Contract\GuestAccess;
use App\Payments\Application\Contract\PayloadVault;
use App\Payments\Application\Contract\TicketPayments;
use App\Shared\Application\IdGenerator;
use App\Shared\Application\UnitOfWork;
use DateInterval;
use Psr\Clock\ClockInterface;

/**
 * Starts a public ticket order. The client names ticket types and quantities, never an amount: the server prices
 * everything. Provider boundary: the reservation commits first, then the hosted checkout is created outside any
 * transaction, so a retry with the same request id returns the same order and checkout. Idempotent on the request id.
 */
final readonly class TicketOrderStarter
{
    public function __construct(
        private TicketRepository $tickets,
        private TicketPayments $payments,
        private TicketIssuer $issuer,
        private PayloadVault $vault,
        private GuestAccess $access,
        private UnitOfWork $transactions,
        private ClockInterface $clock,
        private IdGenerator $ids,
    ) {
    }

    /** @param array<string, int> $quantities ticket type id => number of tickets */
    public function start(string $requestId, string $occurrenceId, array $quantities, ?string $name, string $email): StartedTicketOrder
    {
        $email = self::email($email);
        $name = self::name($name);
        $quantities = self::quantities($quantities);
        $emailHash = $this->access->emailHash($email);
        $requestHash = hash('sha256', json_encode(['ticket-order', $occurrenceId, $quantities, $name, $emailHash], \JSON_THROW_ON_ERROR));

        $order = $this->transactions->execute(fn (): TicketOrder => $this->reserve($requestId, $occurrenceId, $quantities, $name, $email, $emailHash, $requestHash));
        $token = $this->access->token($order->id);
        if ('RESERVED' !== $order->status || $order->isFree()) {
            return new StartedTicketOrder($order->id, $token, null, $order->status, $order->totalMinor);
        }

        return new StartedTicketOrder($order->id, $token, $this->payments->checkout($order->id), $order->status, $order->totalMinor);
    }

    /** @param array<string, int> $quantities */
    private function reserve(string $requestId, string $occurrenceId, array $quantities, string $name, string $email, string $emailHash, string $requestHash): TicketOrder
    {
        $prior = $this->tickets->find($requestId);
        if (null !== $prior) {
            if ($prior->requestHash !== $requestHash) {
                throw EventException::conflict('This request identifier was used for different ticket details.');
            }

            return $prior;
        }
        $now = $this->clock->now();
        $occurrence = $this->tickets->lockOccurrence($occurrenceId) ?? throw EventException::missing('Event');
        $sale = $this->tickets->sale($occurrenceId);
        if (null === $sale || !$sale->isOpen($now) || !$occurrence->sellable($now)) {
            throw EventException::conflict('Tickets for this event are not on sale.');
        }
        $seats = array_sum($quantities);
        if ($seats > $sale->maxPerOrder) {
            throw EventException::invalid('You can buy at most '.$sale->maxPerOrder.' tickets in one order.');
        }
        if (null !== $occurrence->capacity && $this->tickets->seatsTaken($occurrenceId, $now) + $seats > $occurrence->capacity) {
            throw EventException::conflict('There are not enough places left.');
        }
        $types = [];
        foreach ($this->tickets->types($occurrenceId) as $type) {
            $types[$type->id] = $type;
        }
        $lines = [];
        $subtotal = 0;
        foreach ($quantities as $typeId => $quantity) {
            $type = $types[$typeId] ?? null;
            if (!$type instanceof TicketType || !$type->active) {
                throw EventException::invalid('Choose ticket types that are on sale.');
            }
            if (null !== $type->quantityCap && $this->tickets->typeTaken($typeId, $now) + $quantity > $type->quantityCap) {
                throw EventException::conflict('There are not enough '.$type->name.' tickets left.');
            }
            $lines[] = new TicketOrderLine($this->ids->uuid(), $typeId, $type->name, $quantity, $type->priceMinor);
            $subtotal += $quantity * $type->priceMinor;
        }
        $fees = $this->tickets->fees();
        $total = $fees->total($subtotal);
        if ($total > 0 && $total < 500) {
            throw EventException::invalid('A paid order must total ZAR 5.00 or more.');
        }
        $order = new TicketOrder(
            $requestId,
            $occurrenceId,
            'RESERVED',
            $name,
            $this->vault->encrypt($email),
            $emailHash,
            $this->access->hash($this->access->token($requestId)),
            $this->access->hash($this->access->token('wallet-'.$requestId)),
            $requestHash,
            $subtotal,
            $total - $subtotal,
            $total,
            $now->add(new DateInterval('PT'.$fees->holdMinutes.'M')),
            $now,
            null,
            0,
            $lines,
        );
        if (!$this->tickets->insert($order)) {
            throw EventException::conflict('This request identifier was already used.');
        }
        $this->tickets->audit($order->id, 'TICKET_ORDER_RESERVED', 'Public ticket order reserved.', $now);
        if ($order->isFree()) {
            return $this->issuer->issue($order, $now);
        }
        $this->payments->open($order->id, $order->totalMinor, $name, $order->buyerEmailEncrypted, $emailHash, $order->accessHash, $requestHash);

        return $order;
    }

    /**
     * @param array<string, int> $quantities
     *
     * @return array<string, int>
     */
    private static function quantities(array $quantities): array
    {
        $clean = [];
        foreach ($quantities as $typeId => $quantity) {
            $id = strtolower((string) $typeId);
            if (1 !== preg_match('/\A[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}\z/', $id) || $quantity < 1 || $quantity > 50 || isset($clean[$id])) {
                throw EventException::invalid('Choose how many of each ticket type you want.');
            }
            $clean[$id] = $quantity;
        }
        if ([] === $clean) {
            throw EventException::invalid('Choose at least one ticket.');
        }
        ksort($clean);

        return $clean;
    }

    public static function email(string $value): string
    {
        $email = strtolower(trim($value));
        if ('' === $email || \strlen($email) > 254 || false === filter_var($email, \FILTER_VALIDATE_EMAIL) || 1 === preg_match('/[\x00-\x1f\x7f]/', $email)) {
            throw EventException::invalid('Enter a valid email address to receive your tickets.');
        }

        return $email;
    }

    private static function name(?string $value): string
    {
        $name = trim(preg_replace('/\s+/u', ' ', (string) $value) ?? '');
        if ('' === $name || mb_strlen($name) > 100 || 1 === preg_match('/[\x00-\x1f\x7f<>]/', $name)) {
            throw EventException::invalid('Enter your name (at most 100 characters).');
        }

        return $name;
    }
}
