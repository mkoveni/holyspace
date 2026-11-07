<?php

declare(strict_types=1);

namespace App\Giving\Application\Contract;

/** Read side of funds and gifts (Java `GivingReader`); totals join it in G3. */
interface GivingReader
{
    /** Active funds, or all funds when $archived; $search (already trimmed, may be empty) matches the name literally. */
    public function funds(bool $archived, string $search, int $limit, int $offset): FundPage;

    /** Gifts received in the (validated) filter period, newest first; a fund filter selects gifts with that fund. */
    public function gifts(GiftFilter $filter): GiftPage;

    public function gift(string $id): ?GiftDetail;
}
