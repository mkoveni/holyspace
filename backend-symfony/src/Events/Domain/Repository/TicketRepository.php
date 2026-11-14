<?php

declare(strict_types=1);

namespace App\Events\Domain\Repository;

use App\Events\Domain\PublicSale;
use App\Events\Domain\SaleOccurrence;
use App\Events\Domain\TicketFeeSettings;
use App\Events\Domain\TicketOrder;
use App\Events\Domain\TicketType;
use DateTimeImmutable;

/**
 * Public ticketing. Lock order for every write path: the payments order, then the occurrence (`lockOccurrence`), then the
 * ticket order (`lock`), then its tickets.
 */
interface TicketRepository
{
    public function fees(): TicketFeeSettings;

    public function saveFees(TicketFeeSettings $fees): void;

    public function sale(string $occurrenceId): ?PublicSale;

    public function saveSale(PublicSale $sale): void;

    /** @return list<TicketType> */
    public function types(string $occurrenceId): array;

    public function saveType(TicketType $type): void;

    public function lockOccurrence(string $occurrenceId): ?SaleOccurrence;

    /** Confirmed person registrations + live (non-void) tickets + unexpired holds. */
    public function seatsTaken(string $occurrenceId, DateTimeImmutable $now): int;

    /** Live tickets and unexpired holds of one ticket type. */
    public function typeTaken(string $typeId, DateTimeImmutable $now): int;

    public function find(string $orderId): ?TicketOrder;

    public function lock(string $orderId): ?TicketOrder;

    public function byAccess(string $accessHash): ?TicketOrder;

    public function byWallet(string $walletHash): ?TicketOrder;

    public function insert(TicketOrder $order): bool;

    public function setStatus(string $orderId, string $status, ?DateTimeImmutable $paidAt = null): void;

    /** @param list<array{id: string, lineId: string, codeHash: string, holder: string}> $tickets */
    public function issue(string $orderId, array $tickets): void;

    public function voidAll(string $orderId): void;

    /** @return list<array{id: string, lineId: string, typeName: string, holder: string, status: string}> */
    public function tickets(string $orderId): array;

    /** @return list<string> ids of RESERVED orders whose hold has lapsed, oldest first */
    public function lapsed(DateTimeImmutable $now, int $limit): array;

    /** @return array{id: string, deliveryId: string, reviewHash: string, createdAt: DateTimeImmutable}|null the newest tickets email of the order */
    public function email(string $orderId): ?array;

    /** @return array{id: string, orderId: string, deliveryId: string, reviewHash: string}|null by communication id */
    public function emailById(string $id): ?array;

    public function recordEmail(string $id, string $orderId, string $deliveryId, string $reviewHash, DateTimeImmutable $at): void;

    /** @return list<array{id: string, lineId: string, typeName: string, unitPriceMinor: int, holder: string, status: string, creditNoteId: ?string}> */
    public function ticketPrices(string $orderId): array;

    /**
     * Marks the given tickets of the order VOID (already void ones are left alone) and returns how many changed.
     *
     * @param list<string> $ticketIds
     */
    public function voidTickets(string $orderId, array $ticketIds): int;

    /** @param list<string> $ticketIds */
    public function creditTickets(array $ticketIds, string $creditNoteId): void;

    /**
     * Locks and returns the ticket of this occurrence with that code hash, or null.
     *
     * @return array{id: string, orderId: string, typeName: string, holder: string, status: string, checkedInAt: ?DateTimeImmutable, orderStatus: string}|null
     */
    public function lockTicketByCode(string $occurrenceId, string $codeHash): ?array;

    public function unmatchedRefund(string $orderId): int;

    public function setUnmatchedRefund(string $orderId, int $amountMinor): void;

    public function checkIn(string $ticketId, DateTimeImmutable $at): void;

    /** @return array{issued: int, checkedIn: int, void: int} */
    public function scanCounts(string $occurrenceId): array;

    /** @return list<string> ids of the buyer's paid orders for events that have not ended, newest first */
    public function currentOrdersOf(string $emailHash, DateTimeImmutable $now, int $limit): array;

    public function audit(string $orderId, string $action, string $reason, DateTimeImmutable $at): void;
}
