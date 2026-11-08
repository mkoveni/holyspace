<?php

declare(strict_types=1);

namespace App\Events\Application\Contract;

use App\Events\Domain\EventException;
use App\Events\Domain\TimeOfDay;
use App\Shared\Domain\CalendarDate;
use App\Shared\Domain\InvalidInputException;

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

    /**
     * Builds a schedule from wire text (ISO date, HH:mm[:ss[.f]] times) so callers need no Events domain types.
     *
     * @throws CalendarFailure 400 for a malformed date or time
     */
    public static function of(string $venueId, string $timeZone, string $frequency, int $interval, int $count, string $firstDate, string $startsAt, string $endsAt, int $endDayOffset): self
    {
        try {
            return new self(strtolower($venueId), $timeZone, $frequency, $interval, $count, CalendarDate::parse($firstDate), TimeOfDay::parse($startsAt), TimeOfDay::parse($endsAt), $endDayOffset);
        } catch (EventException|InvalidInputException $e) {
            throw new CalendarFailure(400, $e->getMessage());
        }
    }

    /** Java's `CommunityCalendar.Schedule` record text, which callers hash into their request fingerprints. */
    public function javaString(): string
    {
        return sprintf(
            'Schedule[venueId=%s, timeZone=%s, frequency=%s, interval=%d, count=%d, firstDate=%s, startsAt=%s, endsAt=%s, endDayOffset=%d]',
            $this->venueId,
            $this->timeZone,
            $this->frequency,
            $this->interval,
            $this->count,
            $this->firstDate->iso(),
            $this->startsAt->javaString(),
            $this->endsAt->javaString(),
            $this->endDayOffset,
        );
    }
}
