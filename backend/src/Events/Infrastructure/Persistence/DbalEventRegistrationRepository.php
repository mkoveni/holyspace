<?php

declare(strict_types=1);

namespace App\Events\Infrastructure\Persistence;

use App\Events\Domain\Model\EventRegistration;
use App\Events\Domain\Model\RegistrationSettings;
use App\Events\Domain\Repository\EventRegistrationRepository;
use App\Shared\Domain\Model\WorkflowRecord;
use App\Shared\Infrastructure\Persistence\WorkflowAuditJournal;
use Doctrine\DBAL\Connection;
use DomainException;
use InvalidArgumentException;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

#[AsAlias(EventRegistrationRepository::class)]
final readonly class DbalEventRegistrationRepository implements EventRegistrationRepository
{
    public function __construct(private Connection $db, private WorkflowAuditJournal $audit)
    {
    }

    public function settings(string $occurrenceId): RegistrationSettings
    {
        $payload = $this->db->fetchOne('SELECT payload FROM event_registration_settings WHERE id = ?', [$occurrenceId]);

        return false === $payload ? new RegistrationSettings($occurrenceId) : new RegistrationSettings(...json_decode($payload, true, flags: JSON_THROW_ON_ERROR));
    }

    public function saveSettings(RegistrationSettings $settings): void
    {
        $this->persist('event_registration_settings', 'registration_settings', $settings, []);
    }

    public function find(string $id): ?EventRegistration
    {
        return $this->hydrate($this->db->fetchOne('SELECT payload FROM event_registrations WHERE id = ?', [$id]));
    }

    public function forPerson(string $occurrenceId, string $personId): ?EventRegistration
    {
        return $this->hydrate($this->db->fetchOne('SELECT payload FROM event_registrations WHERE occurrence_id = ? AND person_id = ?', [$occurrenceId, $personId]));
    }

    public function save(EventRegistration $registration): void
    {
        $this->persist('event_registrations', 'registrations', $registration, ['occurrence_id' => $registration->occurrenceId, 'person_id' => $registration->personId, 'status' => $registration->status, 'queue_token' => $registration->queueToken]);
    }

    public function pendingPayments(string $occurrenceId): array
    {
        return array_map(fn ($payload) => $this->hydrate($payload), $this->db->fetchFirstColumn("SELECT payload FROM event_registrations WHERE occurrence_id=? AND status='pending_payment'", [$occurrenceId]));
    }

    public function counts(string $occurrenceId): array
    {
        $counts = ['confirmed' => 0, 'waitlisted' => 0, 'cancelled' => 0, 'pending_payment' => 0];
        foreach ($this->db->fetchAllAssociative('SELECT status, COUNT(*) AS total FROM event_registrations WHERE occurrence_id = ? GROUP BY status', [$occurrenceId]) as $row) {
            $counts[$row['status']] = (int) $row['total'];
        }

        return $counts;
    }

    public function page(string $occurrenceId, ?string $after, int $limit): array
    {
        $query = $this->db->createQueryBuilder()->select('payload')->from('event_registrations')->where('occurrence_id = :occurrence')->setParameter('occurrence', $occurrenceId)->orderBy('queue_position')->setMaxResults($limit);
        if (null !== $after) {
            $position = $this->db->fetchOne('SELECT queue_position FROM event_registrations WHERE occurrence_id = ? AND queue_token = ?', [$occurrenceId, $after]);
            if (false === $position) {
                throw new InvalidArgumentException('The roster changed. Reload the first page.');
            }
            $query->andWhere('queue_position > :after')->setParameter('after', (int) $position);
        }

        return array_map(fn ($row) => $this->hydrate($row['payload']), $query->executeQuery()->fetchAllAssociative());
    }

    public function nextWaitlisted(string $occurrenceId): ?EventRegistration
    {
        return $this->hydrate($this->db->createQueryBuilder()->select('payload')->from('event_registrations')->where('occurrence_id = :occurrence AND status = :status')->setParameters(['occurrence' => $occurrenceId, 'status' => 'waitlisted'])->orderBy('queue_position')->setMaxResults(1)->executeQuery()->fetchOne());
    }

    private function hydrate(string|false $payload): ?EventRegistration
    {
        return false === $payload ? null : new EventRegistration(...json_decode($payload, true, flags: JSON_THROW_ON_ERROR));
    }

    private function persist(string $table, string $kind, WorkflowRecord $record, array $columns): void
    {
        $this->db->transactional(function () use ($table, $kind, $record, $columns) {
            $payload = get_object_vars($record);
            if ($record instanceof EventRegistration) {
                $oldToken = $this->db->fetchOne('SELECT queue_token FROM event_registrations WHERE id = ?', [$record->id]);
                // WorkflowTransaction holds the shared church lock while assigning the next position.
                // A DB position, rather than UUID ordering, preserves FIFO across PHP processes.
                $columns['queue_position'] = 0 === $record->version || $oldToken !== $record->queueToken
                    ? 1 + (int) $this->db->fetchOne('SELECT COALESCE(MAX(queue_position), 0) FROM event_registrations WHERE occurrence_id = ?', [$record->occurrenceId])
                    : $record->queuePosition;
                $payload['queuePosition'] = $columns['queue_position'];
            }
            $data = $columns + ['payload' => json_encode(array_replace($payload, ['version' => $record->version + 1]), JSON_THROW_ON_ERROR), 'version' => $record->version + 1];
            if (0 === $record->version) {
                $this->db->insert($table, ['id' => $record->id] + $data);
            } elseif (1 !== $this->db->update($table, $data, ['id' => $record->id, 'version' => $record->version])) {
                throw new DomainException('This record changed. Reload and retry.');
            }
            $this->audit->append('events', $kind, $record);
        });
    }
}
