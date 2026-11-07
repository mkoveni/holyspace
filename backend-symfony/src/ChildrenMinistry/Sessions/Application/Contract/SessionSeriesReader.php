<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Sessions\Application\Contract;

use App\ChildrenMinistry\Sessions\Application\ScheduledSession;
use App\ChildrenMinistry\Sessions\Application\SeriesPage;
use App\ChildrenMinistry\Sessions\Application\SeriesSummary;
use DateTimeImmutable;

/** Read side of recurring series. */
interface SessionSeriesReader
{
    /** Whether a non-cancelled session of the class or in the room overlaps [from, until). */
    public function conflicts(string $classId, string $roomId, DateTimeImmutable $from, DateTimeImmutable $until): bool;

    /** Newest first. */
    public function list(int $limit, int $offset): SeriesPage;

    public function summary(string $id): ?SeriesSummary;

    /** @return list<ScheduledSession> by scheduled date */
    public function sessions(string $id): array;
}
