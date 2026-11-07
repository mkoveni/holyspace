<?php

declare(strict_types=1);

namespace App\Events\Infrastructure\Persistence;

use App\Events\Application\Contract\EventPage;
use App\Events\Application\Contract\EventReader;
use App\Events\Application\Contract\EventView;
use App\Events\Application\Contract\SeriesView;
use App\Events\Domain\EventDefinition;
use App\Events\Domain\EventException;
use App\Events\Domain\EventSeries;
use App\Events\Domain\Repository\EventRepository;
use App\Events\Domain\TimeOfDay;
use App\Shared\Domain\CalendarDate;
use App\Shared\Infrastructure\Persistence\LikePattern;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Query\QueryBuilder;

final readonly class DbalEventStore implements EventRepository, EventReader
{
    public function __construct(private Connection $connection)
    {
    }

    public function findEventForUpdate(string $id): ?EventDefinition
    {
        $row = $this->connection->createQueryBuilder()->select('*')->from('event_definitions')->where('id = :id')->setParameter('id', $id)->forUpdate()->fetchAssociative();

        return false === $row ? null : EventDefinition::reconstitute((string) $row['id'], (string) $row['title'], (string) $row['type'], (string) $row['description'], (bool) $row['active'], (int) $row['version']);
    }

    public function findSeriesForUpdate(string $id): ?EventSeries
    {
        $row = $this->connection->createQueryBuilder()->select('*')->from('event_series')->where('id = :id')->setParameter('id', $id)->forUpdate()->fetchAssociative();

        return false === $row ? null : EventSeries::reconstitute(
            (string) $row['id'],
            (string) $row['event_id'],
            (string) $row['time_zone'],
            (string) $row['frequency'],
            (int) $row['repeat_interval'],
            (int) $row['occurrence_count'],
            CalendarDate::parse((string) $row['first_date']),
            TimeOfDay::parse((string) $row['starts_at']),
            TimeOfDay::parse((string) $row['ends_at']),
            (int) $row['end_day_offset'],
            (string) $row['venue_id'],
            (int) $row['version'],
        );
    }

    public function insertEvent(EventDefinition $event): void
    {
        $this->connection->insert('event_definitions', [
            'id' => $event->id,
            'title' => $event->title,
            'type' => $event->type,
            'description' => $event->description,
            'active' => $event->active,
            'version' => $event->version,
        ], ['active' => ParameterType::BOOLEAN]);
    }

    public function saveEvent(EventDefinition $event): void
    {
        $changed = $this->connection->update('event_definitions', [
            'title' => $event->title,
            'type' => $event->type,
            'description' => $event->description,
            'active' => $event->active,
            'version' => $event->version,
        ], ['id' => $event->id, 'version' => $event->version - 1], ['active' => ParameterType::BOOLEAN]);
        if (1 !== $changed) {
            throw EventException::conflict('Event changed. Refresh first.');
        }
    }

    public function insertSeries(EventSeries $series): void
    {
        $this->connection->insert('event_series', [
            'id' => $series->id,
            'event_id' => $series->eventId,
            'time_zone' => $series->timeZone,
            'frequency' => $series->frequency,
            'repeat_interval' => $series->interval,
            'occurrence_count' => $series->count,
            'first_date' => $series->firstDate->iso(),
            'starts_at' => $series->startsAt->iso(),
            'ends_at' => $series->endsAt->iso(),
            'end_day_offset' => $series->endDayOffset,
            'venue_id' => $series->venueId,
            'version' => $series->version,
        ]);
    }

    public function saveSeries(EventSeries $series): void
    {
        $changed = $this->connection->update('event_series', [
            'starts_at' => $series->startsAt->iso(),
            'ends_at' => $series->endsAt->iso(),
            'end_day_offset' => $series->endDayOffset,
            'venue_id' => $series->venueId,
            'version' => $series->version,
        ], ['id' => $series->id, 'version' => $series->version - 1]);
        if (1 !== $changed) {
            throw EventException::conflict('Series changed. Refresh first.');
        }
    }

    public function find(string $id): ?EventView
    {
        $row = $this->connection->createQueryBuilder()->select('*')->from('event_definitions')->where('id = :id')->setParameter('id', $id)->fetchAssociative();

        return false === $row ? null : self::event($row);
    }

    public function search(string $search, bool $includeArchived, int $limit, int $offset): EventPage
    {
        $rows = $this->matching($search, $includeArchived)->select('*')->orderBy('title')->addOrderBy('id')->setMaxResults($limit)->setFirstResult($offset)->fetchAllAssociative();
        $total = $this->matching($search, $includeArchived)->select('COUNT(*)')->fetchOne();

        return new EventPage(array_values(array_map(self::event(...), $rows)), (int) $total);
    }

    public function findSeries(string $id): ?SeriesView
    {
        $row = $this->connection->createQueryBuilder()->select('*')->from('event_series')->where('id = :id')->setParameter('id', $id)->fetchAssociative();

        return false === $row ? null : self::series($row);
    }

    public function seriesFor(string $eventId, int $limit, int $offset): array
    {
        $rows = $this->connection->createQueryBuilder()
            ->select('*')->from('event_series')->where('event_id = :event')->setParameter('event', $eventId)
            ->orderBy('first_date')->addOrderBy('id')->setMaxResults($limit)->setFirstResult($offset)
            ->fetchAllAssociative();

        return array_values(array_map(self::series(...), $rows));
    }

    /** @param array<string, mixed> $row */
    private static function series(array $row): SeriesView
    {
        return new SeriesView(
            (string) $row['id'],
            (string) $row['event_id'],
            (string) $row['time_zone'],
            (string) $row['frequency'],
            (int) $row['repeat_interval'],
            (int) $row['occurrence_count'],
            (string) $row['first_date'],
            TimeOfDay::parse((string) $row['starts_at'])->iso(),
            TimeOfDay::parse((string) $row['ends_at'])->iso(),
            (int) $row['end_day_offset'],
            (string) $row['venue_id'],
            (int) $row['version'],
        );
    }

    public function seriesCount(string $eventId): int
    {
        return (int) $this->connection->fetchOne('SELECT COUNT(*) FROM event_series WHERE event_id = ?', [$eventId]);
    }

    /** @param array<string, mixed> $row */
    private static function event(array $row): EventView
    {
        return new EventView((string) $row['id'], (string) $row['title'], (string) $row['type'], (string) $row['description'], (bool) $row['active'], (int) $row['version']);
    }

    private function matching(string $search, bool $includeArchived): QueryBuilder
    {
        $query = $this->connection->createQueryBuilder()->from('event_definitions')->where('title ILIKE :pattern')->setParameter('pattern', LikePattern::contains($search));
        if (!$includeArchived) {
            $query->andWhere('active');
        }

        return $query;
    }
}
