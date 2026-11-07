<?php

declare(strict_types=1);

namespace App\Tests\Events\Application;

use App\Events\Application\Contract\EventPage;
use App\Events\Application\Contract\EventReader;
use App\Events\Application\Contract\EventView;
use App\Events\Application\Contract\SeriesView;
use App\Events\Domain\EventDefinition;
use App\Events\Domain\EventSeries;
use App\Events\Domain\Repository\EventRepository;

/** In-memory test double: no database. */
final class InMemoryEventRepository implements EventRepository, EventReader
{
    /** @var array<string, EventDefinition> */
    public array $events = [];
    /** @var array<string, EventSeries> */
    public array $series = [];

    public function __construct(private readonly LockLog $log = new LockLog())
    {
    }

    public function findEventForUpdate(string $id): ?EventDefinition
    {
        $this->log->locks[] = 'event:'.$id;

        return $this->events[$id] ?? null;
    }

    public function findSeriesForUpdate(string $id): ?EventSeries
    {
        $this->log->locks[] = 'series:'.$id;

        return $this->series[$id] ?? null;
    }

    public function insertEvent(EventDefinition $event): void
    {
        $this->events[$event->id] = $event;
    }

    public function saveEvent(EventDefinition $event): void
    {
        $this->events[$event->id] = $event;
    }

    public function insertSeries(EventSeries $series): void
    {
        $this->series[$series->id] = $series;
    }

    public function saveSeries(EventSeries $series): void
    {
        $this->series[$series->id] = $series;
    }

    public function find(string $id): ?EventView
    {
        $e = $this->events[$id] ?? null;

        return null === $e ? null : new EventView($e->id, $e->title, $e->type, $e->description, $e->active, $e->version);
    }

    public function search(string $search, bool $includeArchived, int $limit, int $offset): EventPage
    {
        return new EventPage([], 0);
    }

    public function seriesFor(string $eventId, int $limit, int $offset): array
    {
        return [];
    }

    public function findSeries(string $id): ?SeriesView
    {
        $s = $this->series[$id] ?? null;

        return null === $s ? null : new SeriesView($s->id, $s->eventId, $s->timeZone, $s->frequency, $s->interval, $s->count, $s->firstDate->iso(), $s->startsAt->iso(), $s->endsAt->iso(), $s->endDayOffset, $s->venueId, $s->version);
    }

    public function seriesCount(string $eventId): int
    {
        return 0;
    }
}
