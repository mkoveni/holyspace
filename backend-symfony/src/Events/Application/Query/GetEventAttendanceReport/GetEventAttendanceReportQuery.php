<?php

declare(strict_types=1);

namespace App\Events\Application\Query\GetEventAttendanceReport;

use DateTimeImmutable;

final readonly class GetEventAttendanceReportQuery
{
    public function __construct(
        public ?string $eventId,
        public string $search,
        public DateTimeImmutable $from,
        public DateTimeImmutable $until,
        public int $limit,
        public int $offset,
    ) {
    }
}
