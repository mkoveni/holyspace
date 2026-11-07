<?php

declare(strict_types=1);

namespace App\Events\Infrastructure\Persistence;

use App\Events\Application\Contract\AttendanceCounts;
use App\Events\Application\Contract\AttendanceHistoryEntry;
use App\Events\Application\Contract\AttendanceReader;
use App\Events\Application\Contract\AttendanceView;
use App\Events\Application\Contract\RegistrationView;
use App\Events\Application\Contract\RosterRow;
use App\Events\Domain\Attendance;
use App\Events\Domain\EventException;
use App\Events\Domain\Repository\AttendanceRepository;
use App\Shared\Infrastructure\Persistence\Timestamps;
use DateTimeImmutable;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Symfony\Component\Uid\Uuid;

final readonly class DbalAttendanceStore implements AttendanceReader, AttendanceRepository
{
    /** Roster state, derived from the booking, the attendance record and whether the occurrence has ended. */
    private const string STATE = "CASE
        WHEN a.status = 'PRESENT' THEN 'PRESENT'
        WHEN r.status = 'WAITLISTED' THEN 'WAITLISTED'
        WHEN r.status = 'CANCELLED' THEN 'CANCELLED'
        WHEN r.status = 'CONFIRMED' AND o.status = 'SCHEDULED' AND o.ends_at <= :now AND (a.status IS NULL OR a.status <> 'PRESENT') THEN 'ABSENT'
        ELSE 'EXPECTED' END";

    public function __construct(private Connection $connection)
    {
    }

    public function findByRegistration(string $registrationId): ?Attendance
    {
        $row = $this->connection->fetchAssociative('SELECT * FROM event_attendance WHERE registration_id = ?', [$registrationId]);

        return false === $row ? null : Attendance::reconstitute(
            (string) $row['id'],
            (string) $row['registration_id'],
            Timestamps::fromDatabase((string) $row['arrived_at']),
            (string) $row['status'],
            (int) $row['version'],
        );
    }

    public function insert(Attendance $attendance): void
    {
        $this->connection->insert('event_attendance', [
            'id' => $attendance->id,
            'registration_id' => $attendance->registrationId,
            'arrived_at' => Timestamps::toDatabase($attendance->arrivedAt),
            'status' => $attendance->status,
            'version' => $attendance->version,
        ]);
    }

    public function save(Attendance $attendance): void
    {
        $changed = $this->connection->update('event_attendance', ['status' => $attendance->status, 'version' => $attendance->version], ['id' => $attendance->id, 'version' => $attendance->version - 1]);
        if (1 !== $changed) {
            throw EventException::conflict('Attendance changed. Refresh before correcting it.');
        }
    }

    public function recordHistory(string $attendanceId, string $actorId, string $action, string $reason, DateTimeImmutable $at): void
    {
        $this->connection->insert('event_attendance_history', [
            'id' => Uuid::v4()->toRfc4122(),
            'attendance_id' => $attendanceId,
            'actor_id' => $actorId,
            'action' => $action,
            'reason' => $reason,
            'occurred_at' => Timestamps::toDatabase($at),
        ]);
    }

    public function isPresent(string $registrationId): bool
    {
        return false !== $this->connection->fetchOne("SELECT 1 FROM event_attendance WHERE registration_id = ? AND status = 'PRESENT'", [$registrationId]);
    }

    public function attended(string $occurrenceId): int
    {
        return (int) $this->connection->fetchOne(
            "SELECT COUNT(*) FROM event_attendance a JOIN event_registrations r ON r.id = a.registration_id WHERE r.occurrence_id = ? AND a.status = 'PRESENT'",
            [$occurrenceId],
        );
    }

    public function roster(string $occurrenceId, ?array $personIds, ?string $status, DateTimeImmutable $now, int $limit, int $offset): array
    {
        if ([] === $personIds) {
            return [];
        }
        [$where, $params, $types] = $this->filter($occurrenceId, $personIds, $status, $now);
        $rows = $this->connection->executeQuery(
            'SELECT r.id AS r_id, r.occurrence_id, r.person_id, r.status AS r_status, r.queue_position, r.version AS r_version, r.created_at, r.updated_at,
                    a.id AS a_id, a.arrived_at, a.status AS a_status, a.version AS a_version, '.self::STATE.' AS state
             FROM event_registrations r JOIN event_occurrences o ON o.id = r.occurrence_id
             LEFT JOIN event_attendance a ON a.registration_id = r.id
             WHERE '.$where.' ORDER BY r.queue_position, r.id LIMIT :limit OFFSET :offset',
            ['now' => Timestamps::toDatabase($now), 'limit' => $limit, 'offset' => $offset] + $params,
            $types,
        )->fetchAllAssociative();

        return array_map(static fn (array $row): RosterRow => new RosterRow(
            new RegistrationView(
                (string) $row['r_id'],
                (string) $row['occurrence_id'],
                (string) $row['person_id'],
                (string) $row['r_status'],
                (int) $row['queue_position'],
                (int) $row['r_version'],
                Timestamps::fromDatabase((string) $row['created_at']),
                Timestamps::fromDatabase((string) $row['updated_at']),
            ),
            null === $row['a_id'] ? null : new AttendanceView((string) $row['a_id'], (string) $row['r_id'], Timestamps::fromDatabase((string) $row['arrived_at']), (string) $row['a_status'], (int) $row['a_version']),
            (string) $row['state'],
        ), $rows);
    }

    public function rosterCount(string $occurrenceId, ?array $personIds, ?string $status, DateTimeImmutable $now): int
    {
        if ([] === $personIds) {
            return 0;
        }
        [$where, $params, $types] = $this->filter($occurrenceId, $personIds, $status, $now);

        return (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM event_registrations r JOIN event_occurrences o ON o.id = r.occurrence_id LEFT JOIN event_attendance a ON a.registration_id = r.id WHERE '.$where,
            $params,
            $types,
        );
    }

    public function counts(string $occurrenceId, DateTimeImmutable $now): AttendanceCounts
    {
        $row = $this->connection->fetchAssociative(
            'SELECT '.self::tallies().'
             FROM event_registrations r JOIN event_occurrences o ON o.id = r.occurrence_id LEFT JOIN event_attendance a ON a.registration_id = r.id
             WHERE r.occurrence_id = :occurrence',
            ['occurrence' => $occurrenceId, 'now' => Timestamps::toDatabase($now)],
        );

        return self::counted(false === $row ? [] : $row);
    }

    public function history(string $registrationId, int $limit, int $offset): ?array
    {
        $attendance = $this->findByRegistration($registrationId);
        if (null === $attendance) {
            return null;
        }
        $rows = $this->connection->createQueryBuilder()
            ->select('*')->from('event_attendance_history')->where('attendance_id = :id')->setParameter('id', $attendance->id)
            ->orderBy('occurred_at', 'DESC')->addOrderBy('id', 'DESC')->setMaxResults($limit)->setFirstResult($offset)
            ->fetchAllAssociative();

        return array_values(array_map(static fn (array $row): AttendanceHistoryEntry => new AttendanceHistoryEntry(
            (string) $row['id'],
            (string) $row['actor_id'],
            (string) $row['action'],
            (string) $row['reason'],
            Timestamps::fromDatabase((string) $row['occurred_at']),
        ), $rows));
    }

    /** The five attendance counters, shared with the report store. */
    public static function tallies(): string
    {
        $absent = "(r.status = 'CONFIRMED' AND o.status = 'SCHEDULED' AND o.ends_at <= :now AND (a.status IS NULL OR a.status <> 'PRESENT'))";

        return "COALESCE(SUM(CASE WHEN r.status = 'CONFIRMED' THEN 1 ELSE 0 END), 0) AS confirmed,
                COALESCE(SUM(CASE WHEN r.status = 'WAITLISTED' THEN 1 ELSE 0 END), 0) AS waitlisted,
                COALESCE(SUM(CASE WHEN r.status = 'CANCELLED' THEN 1 ELSE 0 END), 0) AS cancelled,
                COALESCE(SUM(CASE WHEN a.status = 'PRESENT' THEN 1 ELSE 0 END), 0) AS attended,
                COALESCE(SUM(CASE WHEN $absent THEN 1 ELSE 0 END), 0) AS absent";
    }

    /** @param array<string, mixed> $row */
    public static function counted(array $row): AttendanceCounts
    {
        return new AttendanceCounts((int) ($row['confirmed'] ?? 0), (int) ($row['waitlisted'] ?? 0), (int) ($row['cancelled'] ?? 0), (int) ($row['attended'] ?? 0), (int) ($row['absent'] ?? 0));
    }

    /**
     * @param list<string>|null $personIds
     *
     * @return array{string, array<string, mixed>, array<string, mixed>}
     */
    private function filter(string $occurrenceId, ?array $personIds, ?string $status, DateTimeImmutable $now): array
    {
        $where = 'r.occurrence_id = :occurrence';
        $params = ['occurrence' => $occurrenceId];
        $types = [];
        if (null !== $personIds) {
            $where .= ' AND r.person_id IN (:people)';
            $params['people'] = $personIds;
            $types['people'] = ArrayParameterType::STRING;
        }
        if (null !== $status) {
            $where .= ' AND ('.self::STATE.') = :status';
            $params['status'] = $status;
            $params['now'] = Timestamps::toDatabase($now);
        }

        return [$where, $params, $types];
    }
}
