<?php

declare(strict_types=1);

namespace App\Events\Application;

use App\Events\Domain\EventSeries;
use App\Events\Domain\TimeOfDay;
use App\Shared\Domain\CalendarDate;

/** A proposed recurring schedule, as typed by the planner and reviewed through a preview. */
final readonly class ScheduleDraft
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

    public function series(string $id, string $eventId): EventSeries
    {
        return EventSeries::create($id, $eventId, $this->timeZone, $this->frequency, $this->interval, $this->count, $this->firstDate, $this->startsAt, $this->endsAt, $this->endDayOffset, $this->venueId);
    }
}
