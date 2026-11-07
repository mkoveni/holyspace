<?php

declare(strict_types=1);

namespace App\Events\Domain;

use App\Shared\Domain\CalendarDate;

final readonly class EventSeries
{
    private const array FREQUENCIES = ['ONCE', 'DAILY', 'WEEKLY', 'MONTHLY'];

    public string $id;
    public string $eventId;
    public string $venueId;

    private function __construct(
        string $id,
        string $eventId,
        public string $timeZone,
        public string $frequency,
        public int $interval,
        public int $count,
        public CalendarDate $firstDate,
        public TimeOfDay $startsAt,
        public TimeOfDay $endsAt,
        public int $endDayOffset,
        string $venueId,
        public int $version,
    ) {
        $this->id = EventRules::id($id);
        $this->eventId = EventRules::id($eventId);
        $this->venueId = EventRules::id($venueId);
        EventRules::zone($timeZone);
        if (!\in_array($frequency, self::FREQUENCIES, true) || $interval < 1 || $interval > 52 || $count < 1 || $count > 104
            || $endDayOffset < 0 || $endDayOffset > 7 || ('ONCE' === $frequency && 1 !== $count)) {
            throw EventException::invalid('Choose a recurrence, 1–104 dates, times and an end day offset of 0–7.');
        }
    }

    public static function create(
        string $id,
        string $eventId,
        string $timeZone,
        string $frequency,
        int $interval,
        int $count,
        CalendarDate $firstDate,
        TimeOfDay $startsAt,
        TimeOfDay $endsAt,
        int $endDayOffset,
        string $venueId,
    ): self {
        return new self($id, $eventId, $timeZone, $frequency, $interval, $count, $firstDate, $startsAt, $endsAt, $endDayOffset, $venueId, 0);
    }

    public static function reconstitute(
        string $id,
        string $eventId,
        string $timeZone,
        string $frequency,
        int $interval,
        int $count,
        CalendarDate $firstDate,
        TimeOfDay $startsAt,
        TimeOfDay $endsAt,
        int $endDayOffset,
        string $venueId,
        int $version,
    ): self {
        return new self($id, $eventId, $timeZone, $frequency, $interval, $count, $firstDate, $startsAt, $endsAt, $endDayOffset, $venueId, $version);
    }

    /** New default times and venue for future dates; the recurrence itself never changes. */
    public function defaults(TimeOfDay $start, TimeOfDay $end, int $offset, string $venueId): self
    {
        return new self($this->id, $this->eventId, $this->timeZone, $this->frequency, $this->interval, $this->count, $this->firstDate, $start, $end, $offset, $venueId, $this->version + 1);
    }

    public function touch(): self
    {
        return $this->defaults($this->startsAt, $this->endsAt, $this->endDayOffset, $this->venueId);
    }
}
