<?php

declare(strict_types=1);

namespace App\Events\Application\Contract;

interface VenueReader
{
    public function find(string $id): ?VenueView;

    /** Venues whose name contains the text, active ones only unless archived are included. */
    public function search(string $search, bool $includeArchived, int $limit, int $offset): VenuePage;
}
