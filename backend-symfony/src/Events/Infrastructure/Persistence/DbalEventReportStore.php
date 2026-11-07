<?php

declare(strict_types=1);

namespace App\Events\Infrastructure\Persistence;

use App\Events\Application\Contract\EventReportReader;
use App\Events\Application\Contract\ReportRow;
use App\Events\Application\Contract\ReportTotals;
use App\Shared\Infrastructure\Persistence\LikePattern;
use App\Shared\Infrastructure\Persistence\Timestamps;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;

final readonly class DbalEventReportStore implements EventReportReader
{
    private const string FROM = 'FROM event_occurrences o
        JOIN event_definitions e ON e.id = o.event_id
        LEFT JOIN event_registrations r ON r.occurrence_id = o.id
        LEFT JOIN event_attendance a ON a.registration_id = r.id';

    public function __construct(private Connection $connection)
    {
    }

    public function rows(?string $eventId, string $search, DateTimeImmutable $from, DateTimeImmutable $until, DateTimeImmutable $now, int $limit, int $offset): array
    {
        [$where, $params] = $this->filter($eventId, $search, $from, $until);
        $rows = $this->connection->executeQuery(
            'SELECT o.id, o.event_id, e.title, o.starts_at, o.ends_at, o.time_zone, o.venue_name, o.status, '.DbalAttendanceStore::tallies().'
             '.self::FROM.' WHERE '.$where.'
             GROUP BY o.id, o.event_id, e.title, o.starts_at, o.ends_at, o.time_zone, o.venue_name, o.status
             ORDER BY o.starts_at, o.id LIMIT :limit OFFSET :offset',
            $params + ['now' => Timestamps::toDatabase($now), 'limit' => $limit, 'offset' => $offset],
        )->fetchAllAssociative();

        return array_map(static fn (array $row): ReportRow => new ReportRow(
            (string) $row['id'],
            (string) $row['event_id'],
            (string) $row['title'],
            Timestamps::fromDatabase((string) $row['starts_at']),
            Timestamps::fromDatabase((string) $row['ends_at']),
            (string) $row['time_zone'],
            (string) $row['venue_name'],
            (string) $row['status'],
            DbalAttendanceStore::counted($row),
        ), $rows);
    }

    public function totals(?string $eventId, string $search, DateTimeImmutable $from, DateTimeImmutable $until, DateTimeImmutable $now): ReportTotals
    {
        [$where, $params] = $this->filter($eventId, $search, $from, $until);
        $row = $this->connection->fetchAssociative(
            'SELECT COUNT(DISTINCT o.id) AS occurrences, '.DbalAttendanceStore::tallies().' '.self::FROM.' WHERE '.$where,
            $params + ['now' => Timestamps::toDatabase($now)],
        );
        $row = false === $row ? [] : $row;

        return new ReportTotals((int) ($row['occurrences'] ?? 0), (int) ($row['confirmed'] ?? 0), (int) ($row['waitlisted'] ?? 0), (int) ($row['cancelled'] ?? 0), (int) ($row['attended'] ?? 0), (int) ($row['absent'] ?? 0));
    }

    /** @return array{string, array<string, mixed>} */
    private function filter(?string $eventId, string $search, DateTimeImmutable $from, DateTimeImmutable $until): array
    {
        $where = 'o.starts_at < :until AND o.ends_at > :from';
        $params = ['until' => Timestamps::toDatabase($until), 'from' => Timestamps::toDatabase($from)];
        if (null !== $eventId) {
            $where .= ' AND o.event_id = :event';
            $params['event'] = $eventId;
        }
        if ('' !== trim($search)) {
            $where .= ' AND e.title ILIKE :pattern';
            $params['pattern'] = LikePattern::contains($search);
        }

        return [$where, $params];
    }
}
