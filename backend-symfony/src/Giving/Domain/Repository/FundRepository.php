<?php

declare(strict_types=1);

namespace App\Giving\Domain\Repository;

use App\Giving\Domain\Fund;

interface FundRepository
{
    public function findForUpdate(string $id): ?Fund;

    /**
     * The fund id when a create request with this id was already stored with the same hash, null when none was.
     * Throws a conflict when the id was used for different details.
     */
    public function createdBy(string $requestId, string $hash): ?string;

    /** Inserts unless the id or the (case- and space-insensitive) name is taken; returns whether a row was written. */
    public function insert(Fund $fund, string $hash): bool;

    /** Saves the next version. Throws a conflict when the stored version is not the previous one. */
    public function save(Fund $fund): void;

    /** Offers the fund to donor self-service or withdraws it (`giving_online_funds`). */
    public function offerOnline(string $fundId, bool $enabled): void;
}
