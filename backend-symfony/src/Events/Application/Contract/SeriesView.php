<?php

declare(strict_types=1);

namespace App\Events\Application\Contract;

final readonly class SeriesView
{
    public function __construct(
        public string $id,
        public string $eventId,
        public string $timeZone,
        public string $frequency,
        public int $interval,
        public int $count,
        public string $firstDate,
        public string $startsAt,
        public string $endsAt,
        public int $endDayOffset,
        public string $venueId,
        public int $version,
    ) {
    }
}
