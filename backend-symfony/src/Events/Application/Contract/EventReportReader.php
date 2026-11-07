<?php

declare(strict_types=1);

namespace App\Events\Application\Contract;

use DateTimeImmutable;

interface EventReportReader
{
    /** @return list<ReportRow> occurrences overlapping the window, by start time */
    public function rows(?string $eventId, string $search, DateTimeImmutable $from, DateTimeImmutable $until, DateTimeImmutable $now, int $limit, int $offset): array;

    public function totals(?string $eventId, string $search, DateTimeImmutable $from, DateTimeImmutable $until, DateTimeImmutable $now): ReportTotals;
}
