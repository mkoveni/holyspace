<?php

declare(strict_types=1);

namespace App\Events\Application\Contract;

use DateTimeImmutable;

/** Read side of public ticketing: what the public site may show. Never exact seat counts, only whether tickets remain. */
interface TicketCatalog
{
    /** @return list<array{occurrenceId: string, title: string, startsAt: DateTimeImmutable, endsAt: DateTimeImmutable, timeZone: string, venueName: string, coverImageId: ?string, bannerImageId: ?string, fromPriceMinor: ?int, available: bool}> */
    public function onSale(DateTimeImmutable $now): array;

    /**
     * @return array{occurrenceId: string, title: string, description: string, startsAt: DateTimeImmutable, endsAt: DateTimeImmutable, timeZone: string, venueName: string, venueAddress: string, coverImageId: ?string, bannerImageId: ?string, maxPerOrder: int, available: bool, types: list<array{id: string, name: string, description: string, priceMinor: int, available: bool}>}|null
     */
    public function detail(string $occurrenceId, DateTimeImmutable $now): ?array;

    /** @return array{title: string, startsAt: DateTimeImmutable, endsAt: DateTimeImmutable, timeZone: string, venueName: string, venueAddress: string}|null */
    public function event(string $occurrenceId): ?array;

    /** @return list<array{id: string, status: string, buyerName: string, buyerEmailEncrypted: string, seats: int, totalMinor: int, createdAt: DateTimeImmutable}> newest first */
    public function orders(string $occurrenceId, int $limit): array;

    /**
     * Sales and door totals of one occurrence, for staff (design section 6.6 reports).
     *
     * @return array{types: list<array{name: string, sold: int, checkedIn: int, void: int, grossMinor: int}>, orders: int, paidOrders: int, ticketsMinor: int, feesMinor: int, refundedMinor: int, checkedIn: int, awaitingRefundReview: int}
     */
    public function report(string $occurrenceId): array;
}
