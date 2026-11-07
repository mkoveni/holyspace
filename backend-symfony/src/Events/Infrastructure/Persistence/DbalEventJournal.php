<?php

declare(strict_types=1);

namespace App\Events\Infrastructure\Persistence;

use App\Events\Application\Contract\EventJournal;
use App\Events\Application\Contract\EventJournalReader;
use App\Events\Application\Contract\JournalRequest;
use App\Events\Application\Contract\OccurrenceHistoryEntry;
use App\Events\Application\Contract\RegistrationHistoryEntry;
use App\Events\Domain\EventException;
use App\Events\Domain\EventOccurrence;
use App\Shared\Infrastructure\Persistence\DbalAuditTable;
use App\Shared\Infrastructure\Persistence\Timestamps;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Symfony\Component\Uid\Uuid;

final readonly class DbalEventJournal implements EventJournal, EventJournalReader
{
    private DbalAuditTable $audit;

    public function __construct(private Connection $connection)
    {
        $this->audit = new DbalAuditTable($connection, 'event_audit');
    }

    public function record(JournalRequest $request): void
    {
        try {
            $this->connection->insert('event_requests', [
                'id' => $request->id,
                'kind' => $request->kind,
                'request_hash' => $request->hash,
                'target_id' => $request->targetId,
                'actor_id' => $request->actorId,
                'created_at' => Timestamps::toDatabase($request->createdAt),
            ]);
        } catch (UniqueConstraintViolationException) {
            throw EventException::conflict('This name or request is already in use');
        }
    }

    public function audit(string $actorId, string $action, string $targetId, string $reason, DateTimeImmutable $at): void
    {
        $this->audit->append($actorId, $action, $targetId, $at, ['reason' => $reason]);
    }

    public function occurrence(string $actorId, string $action, EventOccurrence $occurrence, string $reason, DateTimeImmutable $at): void
    {
        $this->connection->insert('event_occurrence_history', [
            'id' => Uuid::v4()->toRfc4122(),
            'occurrence_id' => $occurrence->id,
            'actor_id' => $actorId,
            'action' => $action,
            'reason' => $reason,
            'starts_at' => Timestamps::toDatabase($occurrence->startsAt),
            'ends_at' => Timestamps::toDatabase($occurrence->endsAt),
            'venue_id' => $occurrence->venueId,
            'venue_name' => $occurrence->venueName,
            'venue_address' => $occurrence->venueAddress,
            'status' => $occurrence->status,
            'version' => $occurrence->version,
            'schedule_revision' => $occurrence->scheduleRevision,
            'occurred_at' => Timestamps::toDatabase($at),
        ]);
    }

    public function request(string $id): ?JournalRequest
    {
        $row = $this->connection->createQueryBuilder()
            ->select('*')
            ->from('event_requests')
            ->where('id = :id')
            ->setParameter('id', $id)
            ->fetchAssociative();

        return false === $row ? null : new JournalRequest(
            (string) $row['id'],
            (string) $row['kind'],
            (string) $row['request_hash'],
            (string) $row['target_id'],
            (string) $row['actor_id'],
            Timestamps::fromDatabase((string) $row['created_at']),
        );
    }

    public function registration(string $actorId, string $action, string $registrationId, string $reason, DateTimeImmutable $at): void
    {
        $this->connection->insert('event_registration_history', [
            'id' => Uuid::v4()->toRfc4122(),
            'registration_id' => $registrationId,
            'actor_id' => $actorId,
            'action' => $action,
            'reason' => $reason,
            'occurred_at' => Timestamps::toDatabase($at),
        ]);
    }

    public function registrationHistory(string $registrationId, int $limit, int $offset): array
    {
        $rows = $this->connection->createQueryBuilder()
            ->select('*')->from('event_registration_history')->where('registration_id = :id')->setParameter('id', $registrationId)
            ->orderBy('occurred_at', 'DESC')->addOrderBy('id')->setMaxResults($limit)->setFirstResult($offset)
            ->fetchAllAssociative();

        return array_values(array_map(static fn (array $row): RegistrationHistoryEntry => new RegistrationHistoryEntry(
            (string) $row['id'],
            (string) $row['action'],
            (string) $row['reason'],
            Timestamps::fromDatabase((string) $row['occurred_at']),
        ), $rows));
    }

    public function occurrenceHistory(string $occurrenceId, int $limit, int $offset): array
    {
        $rows = $this->connection->createQueryBuilder()
            ->select('*')->from('event_occurrence_history')->where('occurrence_id = :id')->setParameter('id', $occurrenceId)
            ->orderBy('version', 'DESC')->addOrderBy('id')->setMaxResults($limit)->setFirstResult($offset)
            ->fetchAllAssociative();

        return array_values(array_map(static fn (array $row): OccurrenceHistoryEntry => new OccurrenceHistoryEntry(
            (string) $row['id'],
            (string) $row['action'],
            (string) $row['reason'],
            Timestamps::fromDatabase((string) $row['starts_at']),
            Timestamps::fromDatabase((string) $row['ends_at']),
            (string) $row['venue_id'],
            (string) $row['venue_name'],
            (string) $row['venue_address'],
            (string) $row['status'],
            (int) $row['version'],
            (int) $row['schedule_revision'],
            Timestamps::fromDatabase((string) $row['occurred_at']),
        ), $rows));
    }
}
