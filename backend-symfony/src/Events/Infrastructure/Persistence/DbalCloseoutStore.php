<?php

declare(strict_types=1);

namespace App\Events\Infrastructure\Persistence;

use App\Events\Application\Contract\CloseoutHistoryEntry;
use App\Events\Application\Contract\CloseoutReader;
use App\Events\Application\Contract\CloseoutView;
use App\Events\Application\Contract\NoticeRef;
use App\Events\Domain\OccurrenceCloseout;
use App\Events\Domain\Repository\CloseoutRepository;
use App\Shared\Infrastructure\Persistence\Timestamps;
use Doctrine\DBAL\Connection;
use Symfony\Component\Uid\Uuid;

final readonly class DbalCloseoutStore implements CloseoutReader, CloseoutRepository
{
    public function __construct(private Connection $connection)
    {
    }

    public function find(string $occurrenceId): ?OccurrenceCloseout
    {
        $view = $this->closeout($occurrenceId);

        return null === $view ? null : OccurrenceCloseout::reconstitute($view->occurrenceId, $view->status, $view->version, $view->note, $view->followUp, $view->reviewHash, $view->updatedBy, $view->updatedAt);
    }

    public function save(OccurrenceCloseout $closeout, int $attended, int $absent, int $waitlisted, int $unresolvedNotices): void
    {
        $updatedAt = null === $closeout->updatedAt ? null : Timestamps::toDatabase($closeout->updatedAt);
        $this->connection->executeStatement(
            'INSERT INTO event_closeouts (occurrence_id, status, version, note, follow_up, review_hash, updated_by, updated_at)
             VALUES (:occurrence, :status, :version, :note, :followUp, :hash, :by, :at)
             ON CONFLICT (occurrence_id) DO UPDATE SET status = EXCLUDED.status, version = EXCLUDED.version, note = EXCLUDED.note,
               follow_up = EXCLUDED.follow_up, review_hash = EXCLUDED.review_hash, updated_by = EXCLUDED.updated_by, updated_at = EXCLUDED.updated_at',
            ['occurrence' => $closeout->occurrenceId, 'status' => $closeout->status, 'version' => $closeout->version, 'note' => $closeout->note, 'followUp' => $closeout->followUp, 'hash' => $closeout->reviewHash, 'by' => $closeout->updatedBy, 'at' => $updatedAt],
        );
        $this->connection->insert('event_closeout_history', [
            'id' => Uuid::v4()->toRfc4122(),
            'occurrence_id' => $closeout->occurrenceId,
            'version' => $closeout->version,
            'action' => 'CLOSED' === $closeout->status ? 'CLOSED' : 'REOPENED',
            'note' => $closeout->note,
            'follow_up' => $closeout->followUp,
            'review_hash' => $closeout->reviewHash,
            'attended' => $attended,
            'absent' => $absent,
            'waitlisted' => $waitlisted,
            'unresolved_notices' => $unresolvedNotices,
            'actor_id' => $closeout->updatedBy,
            'occurred_at' => $updatedAt,
        ]);
    }

    public function closeout(string $occurrenceId): ?CloseoutView
    {
        $row = $this->connection->fetchAssociative('SELECT * FROM event_closeouts WHERE occurrence_id = ?', [$occurrenceId]);

        return false === $row ? null : new CloseoutView(
            (string) $row['occurrence_id'],
            (string) $row['status'],
            (int) $row['version'],
            (string) $row['note'],
            (string) $row['follow_up'],
            trim((string) $row['review_hash']),
            (string) $row['updated_by'],
            Timestamps::fromDatabase((string) $row['updated_at']),
        );
    }

    public function history(string $occurrenceId, int $limit, int $offset): array
    {
        $rows = $this->connection->createQueryBuilder()
            ->select('*')->from('event_closeout_history')->where('occurrence_id = :id')->setParameter('id', $occurrenceId)
            ->orderBy('version', 'DESC')->setMaxResults($limit)->setFirstResult($offset)
            ->fetchAllAssociative();

        return array_values(array_map(static fn (array $row): CloseoutHistoryEntry => new CloseoutHistoryEntry(
            (string) $row['id'],
            (int) $row['version'],
            (string) $row['action'],
            (string) $row['note'],
            (string) $row['follow_up'],
            (int) $row['attended'],
            (int) $row['absent'],
            (int) $row['waitlisted'],
            (int) $row['unresolved_notices'],
            (string) $row['actor_id'],
            '',
            Timestamps::fromDatabase((string) $row['occurred_at']),
        ), $rows));
    }

    public function notices(string $occurrenceId, int $limit): array
    {
        $rows = $this->connection->createQueryBuilder()
            ->select('n.id', 'n.registration_id', 'n.purpose', 'n.registration_version', 'n.schedule_revision', 'n.event_version')
            ->from('event_notifications', 'n')->join('n', 'event_registrations', 'r', 'r.id = n.registration_id')
            ->where('r.occurrence_id = :id')->setParameter('id', $occurrenceId)
            ->orderBy('n.id')->setMaxResults($limit)
            ->fetchAllAssociative();

        return array_values(array_map(static fn (array $row): NoticeRef => new NoticeRef((string) $row['id'], (string) $row['registration_id'], (string) $row['purpose'], (int) $row['registration_version'], (int) $row['schedule_revision'], (int) $row['event_version']), $rows));
    }

    public function currentReminderBookings(string $occurrenceId): int
    {
        return (int) $this->connection->fetchOne(
            "SELECT COUNT(*) FROM event_registrations r
             JOIN event_occurrences o ON o.id = r.occurrence_id JOIN event_definitions e ON e.id = o.event_id
             WHERE r.occurrence_id = ? AND r.status = 'CONFIRMED' AND EXISTS (
               SELECT 1 FROM event_notifications n WHERE n.registration_id = r.id AND n.purpose = 'REMINDER'
                 AND n.registration_version = r.version AND n.schedule_revision = o.schedule_revision AND n.event_version = e.version)",
            [$occurrenceId],
        );
    }

    public function attendanceRevision(string $occurrenceId): int
    {
        return (int) $this->connection->fetchOne(
            'SELECT COALESCE(SUM(a.version + 1), 0) FROM event_attendance a JOIN event_registrations r ON r.id = a.registration_id WHERE r.occurrence_id = ?',
            [$occurrenceId],
        );
    }
}
