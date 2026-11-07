<?php

declare(strict_types=1);

namespace App\Events\Infrastructure\Persistence;

use App\Events\Application\Contract\PolicyView;
use App\Events\Application\Contract\RegistrationCounts;
use App\Events\Application\Contract\RegistrationReader;
use App\Events\Application\Contract\RegistrationView;
use App\Events\Domain\EventException;
use App\Events\Domain\EventRegistration;
use App\Events\Domain\RegistrationPolicy;
use App\Events\Domain\Repository\RegistrationRepository;
use App\Shared\Domain\Revision;
use App\Shared\Infrastructure\Persistence\Timestamps;
use DateTimeImmutable;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;

final readonly class DbalRegistrationStore implements RegistrationRepository, RegistrationReader
{
    public function __construct(private Connection $connection)
    {
    }

    public function findPolicy(string $occurrenceId): ?RegistrationPolicy
    {
        $row = $this->policyRow($occurrenceId);

        return null === $row ? null : RegistrationPolicy::reconstitute($row->occurrenceId, $row->open, $row->capacity, $row->waitlistEnabled, $row->nextPosition, $row->version);
    }

    public function insertPolicy(RegistrationPolicy $policy): void
    {
        $this->connection->insert('event_registration_policies', [
            'occurrence_id' => $policy->occurrenceId,
            'open' => $policy->open,
            'capacity' => $policy->capacity,
            'waitlist_enabled' => $policy->waitlistEnabled,
            'next_position' => $policy->nextPosition,
            'version' => $policy->version,
        ], ['open' => ParameterType::BOOLEAN, 'waitlist_enabled' => ParameterType::BOOLEAN]);
    }

    public function savePolicy(RegistrationPolicy $policy): void
    {
        $changed = $this->connection->update('event_registration_policies', [
            'open' => $policy->open,
            'capacity' => $policy->capacity,
            'waitlist_enabled' => $policy->waitlistEnabled,
            'next_position' => $policy->nextPosition,
            'version' => $policy->version,
        ], ['occurrence_id' => $policy->occurrenceId, 'version' => $policy->version - 1], ['open' => ParameterType::BOOLEAN, 'waitlist_enabled' => ParameterType::BOOLEAN]);
        if (1 !== $changed) {
            throw EventException::conflict('Registration policy changed. Refresh first.');
        }
    }

    public function cancelOccurrenceRegistrations(string $occurrenceId, string $actorId, string $reason, DateTimeImmutable $now): void
    {
        $at = Timestamps::toDatabase($now);
        $active = "SELECT id FROM event_registrations WHERE occurrence_id = :occurrence AND status <> 'CANCELLED'";
        $params = ['occurrence' => $occurrenceId, 'actor' => $actorId, 'reason' => $reason, 'at' => $at];
        $this->connection->executeStatement(
            "INSERT INTO event_registration_history (id, registration_id, actor_id, action, reason, occurred_at)
             SELECT gen_random_uuid(), id, :actor, 'OCCURRENCE_CANCELLED', :reason, :at::timestamptz FROM ($active) active",
            $params,
        );
        $this->connection->executeStatement(
            "UPDATE event_registrations SET status = 'CANCELLED', version = version + 1, updated_at = :at::timestamptz
             WHERE id IN ($active)",
            ['occurrence' => $occurrenceId, 'at' => $at],
        );
    }

    public function findRegistration(string $id): ?EventRegistration
    {
        return $this->aggregate($this->registrationRow('id', $id));
    }

    public function findRegistrationOfPerson(string $occurrenceId, string $personId): ?EventRegistration
    {
        $row = $this->connection->createQueryBuilder()->select('*')->from('event_registrations')
            ->where('occurrence_id = :occurrence')->andWhere('person_id = :person')
            ->setParameter('occurrence', $occurrenceId)->setParameter('person', $personId)->fetchAssociative();

        return $this->aggregate(false === $row ? null : $row);
    }

    public function insertRegistration(EventRegistration $registration): void
    {
        $this->connection->insert('event_registrations', [
            'id' => $registration->id,
            'occurrence_id' => $registration->occurrenceId,
            'person_id' => $registration->personId,
            'status' => $registration->status,
            'queue_position' => $registration->position,
            'version' => $registration->revision->version,
            'created_at' => Timestamps::toDatabase($registration->revision->timeline->createdAt),
            'updated_at' => Timestamps::toDatabase($registration->revision->timeline->updatedAt),
        ]);
    }

    public function saveRegistration(EventRegistration $registration): void
    {
        $changed = $this->connection->update('event_registrations', [
            'status' => $registration->status,
            'queue_position' => $registration->position,
            'version' => $registration->revision->version,
            'updated_at' => Timestamps::toDatabase($registration->revision->timeline->updatedAt),
        ], ['id' => $registration->id, 'version' => $registration->revision->previousVersion()]);
        if (1 !== $changed) {
            throw EventException::conflict('Registration changed. Refresh first.');
        }
    }

    public function promote(string $occurrenceId, int $places, string $actorId, DateTimeImmutable $now): void
    {
        if ($places < 1) {
            return;
        }
        $at = Timestamps::toDatabase($now);
        $chosen = "SELECT id FROM event_registrations WHERE occurrence_id = :occurrence AND status = 'WAITLISTED' ORDER BY queue_position LIMIT :places";
        $types = ['places' => ParameterType::INTEGER];
        $this->connection->executeStatement(
            "INSERT INTO event_registration_history (id, registration_id, actor_id, action, reason, occurred_at)
             SELECT gen_random_uuid(), id, :actor, 'PROMOTED', 'A place became available; promoted in queue order.', :at::timestamptz FROM ($chosen) chosen",
            ['occurrence' => $occurrenceId, 'places' => $places, 'actor' => $actorId, 'at' => $at],
            $types,
        );
        $this->connection->executeStatement(
            "UPDATE event_registrations SET status = 'CONFIRMED', version = version + 1, updated_at = :at::timestamptz WHERE id IN ($chosen)",
            ['occurrence' => $occurrenceId, 'places' => $places, 'at' => $at],
            $types,
        );
    }

    public function find(string $id): ?RegistrationView
    {
        $row = $this->registrationRow('id', $id);

        return null === $row ? null : self::view($row);
    }

    public function registrations(string $occurrenceId, ?string $status, int $limit, int $offset): array
    {
        $query = $this->connection->createQueryBuilder()->select('*')->from('event_registrations')->where('occurrence_id = :occurrence')->setParameter('occurrence', $occurrenceId);
        if (null !== $status) {
            $query->andWhere('status = :status')->setParameter('status', $status);
        }
        $rows = $query->orderBy('queue_position')->addOrderBy('id')->setMaxResults($limit)->setFirstResult($offset)->fetchAllAssociative();

        return array_values(array_map(self::view(...), $rows));
    }

    public function registrationCount(string $occurrenceId, ?string $status): int
    {
        $query = $this->connection->createQueryBuilder()->select('COUNT(*)')->from('event_registrations')->where('occurrence_id = :occurrence')->setParameter('occurrence', $occurrenceId);
        if (null !== $status) {
            $query->andWhere('status = :status')->setParameter('status', $status);
        }

        return (int) $query->fetchOne();
    }

    /** @return array<string, mixed>|null */
    private function registrationRow(string $column, string $value): ?array
    {
        $row = $this->connection->createQueryBuilder()->select('*')->from('event_registrations')->where($column.' = :value')->setParameter('value', $value)->fetchAssociative();

        return false === $row ? null : $row;
    }

    /** @param array<string, mixed>|null $row */
    private function aggregate(?array $row): ?EventRegistration
    {
        if (null === $row) {
            return null;
        }

        return EventRegistration::reconstitute(
            (string) $row['id'],
            (string) $row['occurrence_id'],
            (string) $row['person_id'],
            (string) $row['status'],
            (int) $row['queue_position'],
            Revision::reconstitute((int) $row['version'], Timestamps::fromDatabase((string) $row['created_at']), Timestamps::fromDatabase((string) $row['updated_at'])),
        );
    }

    /** @param array<string, mixed> $row */
    private static function view(array $row): RegistrationView
    {
        return new RegistrationView(
            (string) $row['id'],
            (string) $row['occurrence_id'],
            (string) $row['person_id'],
            (string) $row['status'],
            (int) $row['queue_position'],
            (int) $row['version'],
            Timestamps::fromDatabase((string) $row['created_at']),
            Timestamps::fromDatabase((string) $row['updated_at']),
        );
    }

    public function policy(string $occurrenceId): ?PolicyView
    {
        return $this->policyRow($occurrenceId);
    }

    public function counts(string $occurrenceId): RegistrationCounts
    {
        return $this->countsMany([$occurrenceId])[$occurrenceId] ?? new RegistrationCounts(0, 0, 0);
    }

    public function countsMany(array $occurrenceIds): array
    {
        if ([] === $occurrenceIds) {
            return [];
        }
        $rows = $this->connection->executeQuery(
            'SELECT occurrence_id, status, COUNT(*) AS total FROM event_registrations WHERE occurrence_id IN (?) GROUP BY occurrence_id, status',
            [$occurrenceIds],
            [ArrayParameterType::STRING],
        )->fetchAllAssociative();
        $grouped = [];
        foreach ($rows as $row) {
            $grouped[(string) $row['occurrence_id']][(string) $row['status']] = (int) $row['total'];
        }

        return array_map(static fn (array $states): RegistrationCounts => new RegistrationCounts($states['CONFIRMED'] ?? 0, $states['WAITLISTED'] ?? 0, $states['CANCELLED'] ?? 0), $grouped);
    }

    private function policyRow(string $occurrenceId): ?PolicyView
    {
        $row = $this->connection->createQueryBuilder()->select('*')->from('event_registration_policies')->where('occurrence_id = :id')->setParameter('id', $occurrenceId)->fetchAssociative();

        return false === $row ? null : new PolicyView(
            (string) $row['occurrence_id'],
            (bool) $row['open'],
            null === $row['capacity'] ? null : (int) $row['capacity'],
            (bool) $row['waitlist_enabled'],
            (int) $row['next_position'],
            (int) $row['version'],
        );
    }
}
