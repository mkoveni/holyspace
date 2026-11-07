<?php

declare(strict_types=1);

namespace App\Events\Application\Contract;

use DateTimeImmutable;

final readonly class ReportRow
{
    public function __construct(
        public string $occurrenceId,
        public string $eventId,
        public string $title,
        public DateTimeImmutable $startsAt,
        public DateTimeImmutable $endsAt,
        public string $timeZone,
        public string $venue,
        public string $status,
        public AttendanceCounts $counts,
    ) {
    }
}
