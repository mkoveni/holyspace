<?php

declare(strict_types=1);

namespace App\Events\Infrastructure\Persistence\DBAL;

use App\Events\Domain\Model\ChurchEvent;
use App\Events\Domain\Repository\EventRepository;
use App\Events\Domain\ValueObject\EventId;
use DateTimeImmutable;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use Symfony\Component\Uid\Uuid;

#[AsAlias(EventRepository::class)]
final readonly class DbalEventRepository implements EventRepository
{
    public function __construct(private Connection $db, private EventMapper $mapper)
    {
    }

    public function save(ChurchEvent $event): void
    {
        $this->db->transactional(function () use ($event): void {
            $this->upsert('church_events', $this->mapper->toRow($event));
            foreach ($event->occurrences() as $occurrence) {
                $row = $this->mapper->occurrenceRow($occurrence, $event->id());
                $previous = $this->db->fetchAssociative('SELECT * FROM church_event_occurrences WHERE id = ?', [$row['id']]);
                $this->upsert('church_event_occurrences', $row);
                $types = [];
                if (false === $previous) {
                    $types[] = 'EventOccurrenceScheduled';
                } else {
                    if ($previous['starts_at'] !== $row['starts_at'] || $previous['ends_at'] !== $row['ends_at'] || $previous['location'] !== $row['location']) {
                        $types[] = 'EventOccurrenceRescheduled';
                    }
                    if ($previous['status'] !== $row['status']) {
                        $types[] = 'cancelled' === $row['status'] ? 'EventOccurrenceCancelled' : 'EventOccurrenceRestored';
                    }
                }
                foreach ($types as $type) {
                    $this->db->insert('integration_outbox', ['id' => Uuid::v7()->toRfc4122(), 'type' => $type, 'schema_version' => 1, 'payload' => json_encode(['eventId' => $event->id()->toString(), 'occurrenceId' => $row['id'], 'startsAt' => $row['starts_at'], 'endsAt' => $row['ends_at'], 'location' => $row['location'], 'status' => $row['status'], 'timezone' => $event->timezone(), 'previous' => false === $previous ? null : ['startsAt' => $previous['starts_at'], 'endsAt' => $previous['ends_at'], 'location' => $previous['location']]], JSON_THROW_ON_ERROR), 'created_at' => (new DateTimeImmutable())->format(DATE_ATOM)]);
                }
            }
        });
    }

    private function upsert(string $table, array $row): void
    {
        $exists = $this->db->fetchOne('SELECT id FROM '.$table.' WHERE id = ?', [$row['id']]);
        if (false === $exists) {
            $this->db->insert($table, $row);
        } else {
            $id = $row['id'];
            unset($row['id']);
            $this->db->update($table, $row, ['id' => $id]);
        }
    }

    public function find(EventId $id): ?ChurchEvent
    {
        $row = $this->db->fetchAssociative('SELECT * FROM church_events WHERE id = ?', [$id->toString()]);

        return false === $row ? null : $this->mapper->toDomain($row, $this->db->fetchAllAssociative('SELECT * FROM church_event_occurrences WHERE event_id = ? ORDER BY starts_at, id', [$id->toString()]));
    }

    public function all(): array
    {
        $rows = $this->db->fetchAllAssociative('SELECT * FROM church_events ORDER BY title, id');
        if ([] === $rows) {
            return [];
        }
        $occurrences = $this->db->executeQuery('SELECT * FROM church_event_occurrences WHERE event_id IN (?) ORDER BY starts_at, id', [array_column($rows, 'id')], [ArrayParameterType::STRING])->fetchAllAssociative();
        $byEvent = [];
        foreach ($occurrences as $occurrence) {
            $byEvent[$occurrence['event_id']][] = $occurrence;
        }

        return array_map(fn ($row) => $this->mapper->toDomain($row, $byEvent[$row['id']] ?? []), $rows);
    }
}
