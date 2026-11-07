<?php

declare(strict_types=1);

namespace App\Events\Domain\Repository;

use App\Events\Domain\Venue;

interface VenueRepository
{
    public function findForUpdate(string $id): ?Venue;

    public function findUnlocked(string $id): ?Venue;

    public function insert(Venue $venue): void;

    /** Saves the next version. Throws a conflict when the stored version is not the previous one. */
    public function save(Venue $venue): void;
}
