<?php

declare(strict_types=1);

namespace App\Events\Application;

use App\Events\Domain\TimeOfDay;
use App\Shared\Domain\CalendarDate;

/** A proposed change to one occurrence or to a series' future occurrences, reviewed through a preview. */
final readonly class OccurrenceChangeDraft
{
    public function __construct(
        public string $occurrenceId,
        public int $expectedVersion,
        public ?string $action,
        public ?string $scope,
        public ?CalendarDate $date,
        public ?TimeOfDay $startsAt,
        public ?TimeOfDay $endsAt,
        public int $endDayOffset,
        public ?string $venueId,
    ) {
    }
}
