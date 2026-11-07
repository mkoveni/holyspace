<?php

declare(strict_types=1);

namespace App\Giving\Application\Contract;

/** Funds offered to donor self-service. Active funds are never offered automatically. Published for Payments. */
interface OnlineFundDirectory
{
    public const int CATALOGUE_LIMIT = 500;

    /**
     * Every fund when $management, otherwise only active funds enabled online; ordered by name, then id. Returns at most
     * CATALOGUE_LIMIT + 1 entries so a caller can tell the catalogue is too large.
     *
     * @return list<OnlineFund>
     */
    public function funds(bool $management): array;

    /** Whether donors may give to this fund online now (active and enabled). */
    public function enabled(string $fundId): bool;
}
