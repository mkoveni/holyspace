<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Sessions\Infrastructure\Persistence;

use App\ChildrenMinistry\Sessions\Application\Contract\SessionSeriesReader;
use App\ChildrenMinistry\Sessions\Application\ScheduledSession;
use App\ChildrenMinistry\Sessions\Application\SeriesPage;
use App\ChildrenMinistry\Sessions\Application\SeriesSummary;
use App\ChildrenMinistry\Sessions\Domain\Repository\SessionSeriesRepository;
use App\ChildrenMinistry\Sessions\Domain\SessionSeries;
use App\ChildrenMinistry\Sessions\Domain\SessionStatus;
use App\ChildrenMinistry\Sessions\Domain\WeeklyRecurrence;
use App\ChildrenMinistry\Shared\Domain\ChildrenException;
use App\Shared\Domain\CalendarDate;
use App\Shared\Infrastructure\Persistence\Timestamps;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;

final readonly class DbalSessionSeriesStore implements SessionSeriesReader, SessionSeriesRepository
{
    public function __construct(private Connection $connection)
    {
    }

    public function lockScheduling(): void
    {
        $this->connection->fetchOne('SELECT id FROM children_scheduling_guard WHERE id = TRUE FOR UPDATE');
    }

    public function request(string $actorId, string $requestId): ?SessionSeries
    {
        return $this->seriesRow('created_by = :actor AND request_id = :request', ['actor' => $actorId, 'request' => $requestId], false);
    }

    public function forUpdate(string $id): ?SessionSeries
    {
        return $this->seriesRow('id = :id', ['id' => $id], true);
    }

    public function insert(SessionSeries $series): void
    {
        $this->connection->executeStatement(
            'INSERT INTO children_session_series (id, name, source_session_id, class_id, room_id, seat_limit, time_zone, first_date, starts_at, ends_at, interval_weeks, occurrences, excluded_dates, created_at, created_by, request_id, request_fingerprint, stopped, version)
             VALUES (:id, :name, :source, :class, :room, :seats, :zone, :first, :starts, :ends, :interval, :occurrences, CAST(:excluded AS date[]), :created, :by, :request, :fingerprint, FALSE, :version)',
            [
                'id' => $series->id,
                'name' => $series->name,
                'source' => $series->sourceSessionId,
                'class' => $series->classId,
                'room' => $series->roomId,
                'seats' => $series->seatLimit,
                'zone' => $series->timeZone,
                'first' => $series->recurrence->firstDate->iso(),
                'starts' => $series->recurrence->startsAt,
                'ends' => $series->recurrence->endsAt,
                'interval' => $series->recurrence->intervalWeeks,
                'occurrences' => $series->recurrence->occurrences,
                'excluded' => '{'.implode(',', array_map(static fn (CalendarDate $date): string => $date->iso(), $series->recurrence->excludedDates)).'}',
                'created' => Timestamps::toDatabase($series->createdAt),
                'by' => $series->createdBy,
                'request' => $series->requestId,
                'fingerprint' => $series->requestFingerprint,
                'version' => $series->version,
            ],
        );
    }

    public function update(SessionSeries $series): void
    {
        $changed = $this->connection->executeStatement(
            'UPDATE children_session_series SET stopped = :stopped, version = :version WHERE id = :id AND version = :previous',
            ['stopped' => $series->stopped, 'version' => $series->version, 'id' => $series->id, 'previous' => $series->version - 1],
            ['stopped' => ParameterType::BOOLEAN],
        );
        if (1 !== $changed) {
            throw ChildrenException::conflict('The series changed. Reload before trying again.');
        }
    }

    public function link(string $seriesId, string $sessionId, CalendarDate $date): void
    {
        $this->connection->insert('children_series_sessions', ['series_id' => $seriesId, 'session_id' => $sessionId, 'scheduled_date' => $date->iso()]);
    }

    public function sessionIds(string $seriesId): array
    {
        /** @var list<string> $ids */
        $ids = $this->connection->fetchFirstColumn('SELECT session_id FROM children_series_sessions WHERE series_id = ?', [$seriesId]);

        return array_map(strval(...), $ids);
    }

    public function conflicts(string $classId, string $roomId, DateTimeImmutable $from, DateTimeImmutable $until): bool
    {
        return false !== $this->connection->fetchOne(
            "SELECT 1 FROM children_sessions WHERE status <> 'CANCELLED' AND (class_id = :class OR room_id = :room) AND starts_at < :until AND ends_at > :from LIMIT 1",
            ['class' => $classId, 'room' => $roomId, 'until' => Timestamps::toDatabase($until), 'from' => Timestamps::toDatabase($from)],
        );
    }

    public function list(int $limit, int $offset): SeriesPage
    {
        $rows = $this->connection->fetchAllAssociative($this->summarySql('TRUE').' ORDER BY s.created_at DESC, s.id LIMIT :limit OFFSET :offset', ['limit' => $limit, 'offset' => $offset]);

        return new SeriesPage(array_map(self::summaryOf(...), $rows), (int) $this->connection->fetchOne('SELECT count(*) FROM children_session_series'));
    }

    public function summary(string $id): ?SeriesSummary
    {
        $row = $this->connection->fetchAssociative($this->summarySql('s.id = :id').' LIMIT 1', ['id' => $id]);

        return false === $row ? null : self::summaryOf($row);
    }

    public function sessions(string $id): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT x.id, l.scheduled_date, x.starts_at, x.ends_at, x.status FROM children_series_sessions l JOIN children_sessions x ON x.id = l.session_id WHERE l.series_id = :id ORDER BY l.scheduled_date',
            ['id' => $id],
        );

        return array_map(static fn (array $row): ScheduledSession => new ScheduledSession(
            (string) $row['id'],
            CalendarDate::parse((string) $row['scheduled_date']),
            Timestamps::fromDatabase((string) $row['starts_at']),
            Timestamps::fromDatabase((string) $row['ends_at']),
            SessionStatus::from((string) $row['status']),
        ), $rows);
    }

    private function summarySql(string $where): string
    {
        $count = static fn (string $status): string => 'SELECT count(*) FROM children_series_sessions l JOIN children_sessions x ON x.id = l.session_id WHERE l.series_id = s.id'.('' === $status ? '' : " AND x.status = '$status'");

        return 'SELECT s.id, s.name, s.source_session_id, c.name AS class_name, r.name AS room_name, s.time_zone, s.first_date, s.interval_weeks, s.occurrences,
                  ('.$count('').') AS total_sessions, ('.$count('PLANNED').') AS planned_sessions, ('.$count('CANCELLED').') AS cancelled_sessions, s.stopped, s.version
                FROM children_session_series s
                JOIN children_classes c ON c.id = s.class_id
                JOIN children_rooms r ON r.id = s.room_id
                WHERE '.$where;
    }

    /** @param array<string, mixed> $row */
    private static function summaryOf(array $row): SeriesSummary
    {
        return new SeriesSummary(
            (string) $row['id'],
            (string) $row['name'],
            (string) $row['source_session_id'],
            (string) $row['class_name'],
            (string) $row['room_name'],
            (string) $row['time_zone'],
            CalendarDate::parse((string) $row['first_date']),
            (int) $row['interval_weeks'],
            (int) $row['occurrences'],
            (int) $row['total_sessions'],
            (int) $row['planned_sessions'],
            (int) $row['cancelled_sessions'],
            (bool) $row['stopped'],
            (int) $row['version'],
        );
    }

    /** @param array<string, mixed> $parameters */
    private function seriesRow(string $where, array $parameters, bool $lock): ?SessionSeries
    {
        $row = $this->connection->fetchAssociative('SELECT * FROM children_session_series WHERE '.$where.($lock ? ' FOR UPDATE' : ''), $parameters);
        if (false === $row) {
            return null;
        }
        $excluded = trim((string) $row['excluded_dates'], '{}');

        return SessionSeries::reconstitute(
            (string) $row['id'],
            (string) $row['name'],
            (string) $row['source_session_id'],
            (string) $row['class_id'],
            (string) $row['room_id'],
            (int) $row['seat_limit'],
            (string) $row['time_zone'],
            new WeeklyRecurrence(
                CalendarDate::parse((string) $row['first_date']),
                substr((string) $row['starts_at'], 0, 8),
                substr((string) $row['ends_at'], 0, 8),
                (int) $row['interval_weeks'],
                (int) $row['occurrences'],
                '' === $excluded ? [] : array_map(CalendarDate::parse(...), explode(',', $excluded)),
            ),
            Timestamps::fromDatabase((string) $row['created_at']),
            (string) $row['created_by'],
            (string) $row['request_id'],
            (string) $row['request_fingerprint'],
            (bool) $row['stopped'],
            (int) $row['version'],
        );
    }
}
