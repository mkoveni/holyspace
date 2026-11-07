<?php

declare(strict_types=1);

namespace App\Giving\Application\Contract;

/** Read side of funds, gifts and period totals (Java `GivingReader`). */
interface GivingReader
{
    /** Active funds, or all funds when $archived; $search (already trimmed, may be empty) matches the name literally. */
    public function funds(bool $archived, string $search, int $limit, int $offset): FundPage;

    /** Gifts received in the (validated) filter period, newest first; a fund filter selects gifts with that fund. */
    public function gifts(GiftFilter $filter): GiftPage;

    public function gift(string $id): ?GiftDetail;

    /**
     * Totals of a validated filter per currency, sorted by currency. Gross counts gifts by received date; reversed
     * counts full reversals and provider refunds by their own dates, so net can be negative. A fund filter counts only
     * that fund's allocations.
     *
     * @return list<GivingTotal>
     */
    public function totals(GiftFilter $filter): array;
}
