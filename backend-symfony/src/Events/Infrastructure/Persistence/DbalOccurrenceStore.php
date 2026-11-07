<?php

declare(strict_types=1);

namespace App\Events\Infrastructure\Persistence;

use App\Events\Application\Contract\CalendarEntry;
use App\Events\Application\Contract\OccurrenceReader;
use App\Events\Application\Contract\OccurrenceView;
use App\Events\Domain\EventException;
use App\Events\Domain\EventOccurrence;
use App\Events\Domain\Repository\OccurrenceRepository;
use App\Shared\Domain\CalendarDate;
use App\Shared\Infrastructure\Persistence\LikePattern;
use App\Shared\Infrastructure\Persistence\Timestamps;
use DateTimeImmutable;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Query\QueryBuilder;

final readonly class DbalOccurrenceStore implements OccurrenceRepository, OccurrenceReader
{
    public function __construct(private Connection $connection)
    {
    }

    public function findUnlocked(string $id): ?EventOccurrence
    {
        $row = $this->connection->createQueryBuilder()->select('*')->from('event_occurrences')->where('id = :id')->setParameter('id', $id)->fetchAssociative();

        return false === $row ? null : self::aggregate($row);
    }

    public function find(string $id): ?OccurrenceView
    {
        $occurrence = $this->findUnlocked($id);

        return null === $occurrence ? null : OccurrenceView::of($occurrence);
    }

    public function findForUpdate(string $id): ?EventOccurrence
    {
        $row = $this->connection->createQueryBuilder()->select('*')->from('event_occurrences')->where('id = :id')->setParameter('id', $id)->forUpdate()->fetchAssociative();

        return false === $row ? null : self::aggregate($row);
    }

    public function findFutureOfSeries(string $seriesId, CalendarDate $originFrom, DateTimeImmutable $now, int $limit): array
    {
        $rows = $this->connection->createQueryBuilder()
            ->select('*')->from('event_occurrences')
            ->where('series_id = :series')->andWhere('origin_date >= :origin')->andWhere('ends_at > :now')
            ->setParameter('series', $seriesId)->setParameter('origin', $originFrom->iso())->setParameter('now', Timestamps::toDatabase($now))
            ->orderBy('origin_date')->addOrderBy('id')->setMaxResults($limit)
            ->fetchAllAssociative();

        return array_values(array_map(self::aggregate(...), $rows));
    }

    public function insert(EventOccurrence $occurrence): void
    {
        $this->connection->insert('event_occurrences', [
            'id' => $occurrence->id,
            'event_id' => $occurrence->eventId,
            'series_id' => $occurrence->seriesId,
            'origin_date' => $occurrence->originDate->iso(),
            'time_zone' => $occurrence->timeZone,
            'starts_at' => Timestamps::toDatabase($occurrence->startsAt),
            'ends_at' => Timestamps::toDatabase($occurrence->endsAt),
            'venue_id' => $occurrence->venueId,
            'venue_name' => $occurrence->venueName,
            'venue_address' => $occurrence->venueAddress,
            'exclusive' => $occurrence->exclusive,
            'status' => $occurrence->status,
            'overridden' => $occurrence->overridden,
            'version' => $occurrence->version,
            'schedule_revision' => $occurrence->scheduleRevision,
        ], ['exclusive' => ParameterType::BOOLEAN, 'overridden' => ParameterType::BOOLEAN]);
    }

    public function save(EventOccurrence $occurrence): void
    {
        $changed = $this->connection->update('event_occurrences', [
            'starts_at' => Timestamps::toDatabase($occurrence->startsAt),
            'ends_at' => Timestamps::toDatabase($occurrence->endsAt),
            'venue_id' => $occurrence->venueId,
            'venue_name' => $occurrence->venueName,
            'venue_address' => $occurrence->venueAddress,
            'exclusive' => $occurrence->exclusive,
            'status' => $occurrence->status,
            'overridden' => $occurrence->overridden,
            'version' => $occurrence->version,
            'schedule_revision' => $occurrence->scheduleRevision,
        ], ['id' => $occurrence->id, 'version' => $occurrence->version - 1], ['exclusive' => ParameterType::BOOLEAN, 'overridden' => ParameterType::BOOLEAN]);
        if (1 !== $changed) {
            throw EventException::conflict('Occurrence changed. Refresh first.');
        }
    }

    public function venueClash(string $venueId, DateTimeImmutable $from, DateTimeImmutable $until, array $exclude): bool
    {
        $sql = "SELECT 1 FROM event_occurrences WHERE venue_id = :venue AND status = 'SCHEDULED' AND exclusive AND starts_at < :until AND ends_at > :from";
        $params = ['venue' => $venueId, 'until' => Timestamps::toDatabase($until), 'from' => Timestamps::toDatabase($from)];
        $types = [];
        if ([] !== $exclude) {
            $sql .= ' AND id NOT IN (:exclude)';
            $params['exclude'] = $exclude;
            $types['exclude'] = ArrayParameterType::STRING;
        }

        return false !== $this->connection->fetchOne($sql.' LIMIT 1', $params, $types);
    }

    public function hasFutureScheduled(string $eventId, DateTimeImmutable $now): bool
    {
        return false !== $this->connection->fetchOne(
            "SELECT 1 FROM event_occurrences WHERE event_id = ? AND status = 'SCHEDULED' AND ends_at > ? LIMIT 1",
            [$eventId, Timestamps::toDatabase($now)],
        );
    }

    public function entry(string $id): ?CalendarEntry
    {
        $row = $this->connection->createQueryBuilder()
            ->select('o.*', 'e.title', 'e.type', 'e.active AS event_active')
            ->from('event_occurrences', 'o')->join('o', 'event_definitions', 'e', 'e.id = o.event_id')
            ->where('o.id = :id')->setParameter('id', $id)->fetchAssociative();

        return false === $row ? null : self::entryOf($row);
    }

    public function calendar(?string $eventId, string $search, DateTimeImmutable $from, DateTimeImmutable $until, ?string $status, int $limit, int $offset): array
    {
        $rows = $this->calendarQuery($eventId, $search, $from, $until, $status)
            ->select('o.*', 'e.title', 'e.type', 'e.active AS event_active')
            ->orderBy('o.starts_at')->addOrderBy('o.id')->setMaxResults($limit)->setFirstResult($offset)
            ->fetchAllAssociative();

        return array_values(array_map(self::entryOf(...), $rows));
    }

    public function calendarCount(?string $eventId, string $search, DateTimeImmutable $from, DateTimeImmutable $until, ?string $status): int
    {
        return (int) $this->calendarQuery($eventId, $search, $from, $until, $status)->select('COUNT(*)')->fetchOne();
    }

    private function calendarQuery(?string $eventId, string $search, DateTimeImmutable $from, DateTimeImmutable $until, ?string $status): QueryBuilder
    {
        $query = $this->connection->createQueryBuilder()
            ->from('event_occurrences', 'o')->join('o', 'event_definitions', 'e', 'e.id = o.event_id')
            ->where('o.starts_at < :until')->andWhere('o.ends_at > :from')->andWhere('e.title ILIKE :pattern')
            ->setParameter('until', Timestamps::toDatabase($until))->setParameter('from', Timestamps::toDatabase($from))
            ->setParameter('pattern', LikePattern::contains($search));
        if (null !== $eventId) {
            $query->andWhere('o.event_id = :event')->setParameter('event', $eventId);
        }
        if (null !== $status) {
            $query->andWhere('o.status = :status')->setParameter('status', $status);
        }

        return $query;
    }

    /** @param array<string, mixed> $row */
    private static function entryOf(array $row): CalendarEntry
    {
        return new CalendarEntry(OccurrenceView::of(self::aggregate($row)), (string) $row['title'], (string) $row['type'], (bool) $row['event_active']);
    }

    /** @param array<string, mixed> $row */
    private static function aggregate(array $row): EventOccurrence
    {
        return EventOccurrence::reconstitute(
            (string) $row['id'],
            (string) $row['event_id'],
            (string) $row['series_id'],
            CalendarDate::parse((string) $row['origin_date']),
            (string) $row['time_zone'],
            Timestamps::fromDatabase((string) $row['starts_at']),
            Timestamps::fromDatabase((string) $row['ends_at']),
            (string) $row['venue_id'],
            (string) $row['venue_name'],
            (string) $row['venue_address'],
            (bool) $row['exclusive'],
            (string) $row['status'],
            (bool) $row['overridden'],
            (int) $row['version'],
            (int) $row['schedule_revision'],
        );
    }
}
