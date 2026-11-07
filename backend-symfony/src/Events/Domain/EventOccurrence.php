<?php

declare(strict_types=1);

namespace App\Events\Domain;

use App\Shared\Domain\CalendarDate;
use DateTimeImmutable;

final readonly class EventOccurrence
{
    public string $id;
    public string $eventId;
    public string $seriesId;
    public string $venueId;
    public DateTimeImmutable $startsAt;
    public DateTimeImmutable $endsAt;
    public string $venueName;
    public string $venueAddress;

    private function __construct(
        string $id,
        string $eventId,
        string $seriesId,
        public CalendarDate $originDate,
        public string $timeZone,
        DateTimeImmutable $startsAt,
        DateTimeImmutable $endsAt,
        string $venueId,
        string $venueName,
        string $venueAddress,
        public bool $exclusive,
        public string $status,
        public bool $overridden,
        public int $version,
        public int $scheduleRevision,
    ) {
        $this->id = EventRules::id($id);
        $this->eventId = EventRules::id($eventId);
        $this->seriesId = EventRules::id($seriesId);
        $this->venueId = EventRules::id($venueId);
        EventRules::zone($timeZone);
        $this->startsAt = EventRules::instant($startsAt);
        $this->endsAt = EventRules::instant($endsAt);
        EventRules::period($this->startsAt, $this->endsAt);
        $this->venueName = EventRules::text($venueName, 150);
        $this->venueAddress = EventRules::optional($venueAddress, 1000);
        if (!\in_array($status, ['SCHEDULED', 'CANCELLED'], true)) {
            throw EventException::invalid('Invalid occurrence state.');
        }
    }

    public static function schedule(string $id, EventSeries $series, ScheduleWindow $window, Venue $venue): self
    {
        return new self($id, $series->eventId, $series->id, $window->originDate, $series->timeZone, $window->startsAt, $window->endsAt, $venue->id, $venue->name, $venue->address, $venue->exclusive, 'SCHEDULED', false, 0, 0);
    }

    public static function reconstitute(
        string $id,
        string $eventId,
        string $seriesId,
        CalendarDate $originDate,
        string $timeZone,
        DateTimeImmutable $startsAt,
        DateTimeImmutable $endsAt,
        string $venueId,
        string $venueName,
        string $venueAddress,
        bool $exclusive,
        string $status,
        bool $overridden,
        int $version,
        int $scheduleRevision,
    ): self {
        return new self($id, $eventId, $seriesId, $originDate, $timeZone, $startsAt, $endsAt, $venueId, $venueName, $venueAddress, $exclusive, $status, $overridden, $version, $scheduleRevision);
    }

    public function reschedule(ScheduleWindow $window, Venue $venue, bool $override): self
    {
        if ('SCHEDULED' !== $this->status) {
            throw EventException::conflict('Restore the cancelled occurrence before rescheduling it.');
        }

        return new self($this->id, $this->eventId, $this->seriesId, $this->originDate, $this->timeZone, $window->startsAt, $window->endsAt, $venue->id, $venue->name, $venue->address, $venue->exclusive, $this->status, $override, $this->version + 1, $this->scheduleRevision + 1);
    }

    public function cancel(): self
    {
        if ('CANCELLED' === $this->status) {
            throw EventException::conflict('This occurrence is already cancelled.');
        }

        return $this->withStatus('CANCELLED');
    }

    public function restore(): self
    {
        if ('CANCELLED' !== $this->status) {
            throw EventException::conflict('Only cancelled occurrences can be restored.');
        }

        return $this->withStatus('SCHEDULED');
    }

    private function withStatus(string $next): self
    {
        return new self($this->id, $this->eventId, $this->seriesId, $this->originDate, $this->timeZone, $this->startsAt, $this->endsAt, $this->venueId, $this->venueName, $this->venueAddress, $this->exclusive, $next, true, $this->version + 1, $this->scheduleRevision + 1);
    }
}
