<?php

declare(strict_types=1);

namespace App\Tests\Events\Application;

use App\Events\Application\Contract\CalendarEntry;
use App\Events\Application\Contract\OccurrenceReader;
use App\Events\Application\Contract\OccurrenceView;
use App\Events\Domain\EventOccurrence;
use App\Events\Domain\Repository\OccurrenceRepository;
use App\Shared\Domain\CalendarDate;
use DateTimeImmutable;

/** In-memory test double: no database. */
final class InMemoryOccurrenceStore implements OccurrenceRepository, OccurrenceReader
{
    /** @var array<string, EventOccurrence> */
    public array $occurrences = [];
    public bool $clash = false;
    public bool $future = false;
    /** @var list<string> */
    public array $clashChecks = [];

    public function __construct(private readonly LockLog $log = new LockLog())
    {
    }

    public function findUnlocked(string $id): ?EventOccurrence
    {
        return $this->occurrences[$id] ?? null;
    }

    public function findFutureOfSeries(string $seriesId, CalendarDate $originFrom, DateTimeImmutable $now, int $limit): array
    {
        $found = array_filter($this->occurrences, static fn (EventOccurrence $o): bool => $o->seriesId === $seriesId && $o->originDate->iso() >= $originFrom->iso() && $o->endsAt > $now);
        usort($found, static fn (EventOccurrence $a, EventOccurrence $b): int => [$a->originDate->iso(), $a->id] <=> [$b->originDate->iso(), $b->id]);

        return \array_slice($found, 0, $limit);
    }

    public function findForUpdate(string $id): ?EventOccurrence
    {
        $this->log->locks[] = 'occurrence:'.$id;

        return $this->occurrences[$id] ?? null;
    }

    public function insert(EventOccurrence $occurrence): void
    {
        $this->occurrences[$occurrence->id] = $occurrence;
    }

    public function save(EventOccurrence $occurrence): void
    {
        $this->occurrences[$occurrence->id] = $occurrence;
    }

    public function venueClash(string $venueId, DateTimeImmutable $from, DateTimeImmutable $until, array $exclude): bool
    {
        $this->clashChecks[] = $venueId;

        return $this->clash;
    }

    public function hasFutureScheduled(string $eventId, DateTimeImmutable $now): bool
    {
        return $this->future;
    }

    public function find(string $id): ?OccurrenceView
    {
        return isset($this->occurrences[$id]) ? OccurrenceView::of($this->occurrences[$id]) : null;
    }

    public function entry(string $id): ?CalendarEntry
    {
        return isset($this->occurrences[$id]) ? new CalendarEntry(OccurrenceView::of($this->occurrences[$id]), 'Event', 'SERVICE', true) : null;
    }

    public function calendar(?string $eventId, string $search, DateTimeImmutable $from, DateTimeImmutable $until, ?string $status, int $limit, int $offset): array
    {
        $found = array_filter($this->occurrences, static fn (EventOccurrence $o): bool => (null === $eventId || $o->eventId === $eventId)
            && $o->startsAt < $until && $o->endsAt > $from && (null === $status || $o->status === $status));
        usort($found, static fn (EventOccurrence $a, EventOccurrence $b): int => $a->startsAt <=> $b->startsAt);

        return array_map(static fn (EventOccurrence $o): CalendarEntry => new CalendarEntry(OccurrenceView::of($o), 'Event', 'MEETING', true), \array_slice($found, $offset, $limit));
    }

    public function calendarCount(?string $eventId, string $search, DateTimeImmutable $from, DateTimeImmutable $until, ?string $status): int
    {
        return 0;
    }
}
