<?php

declare(strict_types=1);

namespace App\Tests\ChildrenMinistry\Sessions\Application;

use App\ChildrenMinistry\Sessions\Application\Contract\SessionSeriesReader;
use App\ChildrenMinistry\Sessions\Application\ScheduledSession;
use App\ChildrenMinistry\Sessions\Application\SeriesPage;
use App\ChildrenMinistry\Sessions\Application\SeriesSummary;
use App\ChildrenMinistry\Sessions\Application\SessionSeriesPlanning;
use App\ChildrenMinistry\Sessions\Domain\Repository\SessionSeriesRepository;
use App\ChildrenMinistry\Sessions\Domain\SessionSeries;
use App\ChildrenMinistry\Sessions\Domain\SessionStatus;
use App\Shared\Domain\CalendarDate;
use App\Tests\ChildrenMinistry\Shared\CallLog;
use DateTimeImmutable;
use Psr\Clock\ClockInterface;

/** The session fixture plus in-memory series storage; clashes are derived from the fixture's sessions. */
final class SeriesWorld implements SessionSeriesRepository, SessionSeriesReader
{
    /** @var array<string, SessionSeries> */
    public array $series = [];

    /** @var array<string, array<string, CalendarDate>> series id => session id => date */
    public array $links = [];

    public function __construct(public readonly SessionFixture $world, public readonly CallLog $log)
    {
    }

    public function planning(ClockInterface $clock): SessionSeriesPlanning
    {
        return new SessionSeriesPlanning($this->world, $this, $this->world, $this->world, new SeriesPeople($this->world), $this->world, $clock);
    }

    public function lockScheduling(): void
    {
        $this->log->entries[] = 'lock:scheduling';
    }

    public function request(string $actorId, string $requestId): ?SessionSeries
    {
        foreach ($this->series as $series) {
            if ($series->createdBy === $actorId && $series->requestId === $requestId) {
                return $series;
            }
        }

        return null;
    }

    public function forUpdate(string $id): ?SessionSeries
    {
        $this->log->entries[] = 'forUpdate:series';

        return $this->series[$id] ?? null;
    }

    public function insert(SessionSeries $series): void
    {
        $this->log->entries[] = 'insert:series';
        $this->series[$series->id] = $series;
    }

    public function update(SessionSeries $series): void
    {
        $this->log->entries[] = 'update:series';
        $this->series[$series->id] = $series;
    }

    public function link(string $seriesId, string $sessionId, CalendarDate $date): void
    {
        $this->links[$seriesId][$sessionId] = $date;
    }

    public function sessionIds(string $seriesId): array
    {
        return array_keys($this->links[$seriesId] ?? []);
    }

    public function conflicts(string $classId, string $roomId, DateTimeImmutable $from, DateTimeImmutable $until): bool
    {
        foreach ($this->world->sessions as $session) {
            if (SessionStatus::Cancelled !== $session->status && ($session->classId === $classId || $session->roomId === $roomId) && $session->startsAt < $until && $session->endsAt > $from) {
                return true;
            }
        }

        return false;
    }

    public function list(int $limit, int $offset): SeriesPage
    {
        return new SeriesPage([], 0);
    }

    public function summary(string $id): ?SeriesSummary
    {
        return null;
    }

    public function sessions(string $id): array
    {
        return array_map(fn (string $sessionId): ScheduledSession => new ScheduledSession($sessionId, $this->links[$id][$sessionId], $this->world->sessions[$sessionId]->startsAt, $this->world->sessions[$sessionId]->endsAt, $this->world->sessions[$sessionId]->status), array_keys($this->links[$id] ?? []));
    }
}
