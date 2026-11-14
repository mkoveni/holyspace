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

    /** @return array{id: string, deliveryId: string, reviewHash: string}|null */
    public function email(string $orderId): ?array;

    /** @return array{id: string, orderId: string, deliveryId: string, reviewHash: string}|null by communication id */
    public function emailById(string $id): ?array;

    public function recordEmail(string $id, string $orderId, string $deliveryId, string $reviewHash, DateTimeImmutable $at): void;

    public function audit(string $orderId, string $action, string $reason, DateTimeImmutable $at): void;
}
