<?php

declare(strict_types=1);

namespace App\Events\Application\Contract;

use App\Events\Domain\TimeOfDay;
use App\Shared\Domain\CalendarDate;

/** A recurring meeting schedule proposed by a community. */
final readonly class CommunitySchedule
{
    public function __construct(
        public string $venueId,
        public string $timeZone,
        public string $frequency,
        public int $interval,
        public int $count,
        public CalendarDate $firstDate,
        public TimeOfDay $startsAt,
        public TimeOfDay $endsAt,
        public int $endDayOffset,
    ) {
    }
}
