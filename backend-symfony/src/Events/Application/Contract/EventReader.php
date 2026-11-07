<?php

declare(strict_types=1);

namespace App\Events\Application\Contract;

interface EventReader
{
    public function find(string $id): ?EventView;

    /** Events whose title contains the text; active ones only unless archived are included. */
    public function search(string $search, bool $includeArchived, int $limit, int $offset): EventPage;

    /** @return list<SeriesView> oldest first date first */
    public function seriesFor(string $eventId, int $limit, int $offset): array;

    public function findSeries(string $id): ?SeriesView;

    public function seriesCount(string $eventId): int;
}
