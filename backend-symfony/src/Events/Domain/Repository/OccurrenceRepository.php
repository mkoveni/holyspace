<?php

declare(strict_types=1);

namespace App\Events\Domain\Repository;

use App\Events\Domain\EventOccurrence;
use App\Shared\Domain\CalendarDate;
use DateTimeImmutable;

interface OccurrenceRepository
{
    public function findUnlocked(string $id): ?EventOccurrence;

    /** @return list<EventOccurrence> the series' occurrences from the origin date that have not ended, by origin date */
    public function findFutureOfSeries(string $seriesId, CalendarDate $originFrom, DateTimeImmutable $now, int $limit): array;

    public function findForUpdate(string $id): ?EventOccurrence;

    public function insert(EventOccurrence $occurrence): void;

    public function save(EventOccurrence $occurrence): void;
}
