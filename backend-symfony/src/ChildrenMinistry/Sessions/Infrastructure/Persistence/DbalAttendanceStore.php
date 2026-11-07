<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Sessions\Infrastructure\Persistence;

use App\ChildrenMinistry\Sessions\Application\Contract\AttendanceReader;
use App\ChildrenMinistry\Sessions\Domain\Attendance;
use App\ChildrenMinistry\Sessions\Domain\AttendanceEvent;
use App\ChildrenMinistry\Sessions\Domain\AttendanceSegment;
use App\ChildrenMinistry\Sessions\Domain\Repository\AttendanceRepository;
use App\ChildrenMinistry\Shared\Domain\ChildrenException;
use App\Shared\Infrastructure\Persistence\Timestamps;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;

final readonly class DbalAttendanceStore implements AttendanceReader, AttendanceRepository
{
    private const string TABLE = 'children_attendance';

    public function __construct(private Connection $connection)
    {
    }

    public function attendance(string $id): ?Attendance
    {
        return $this->row($id, false);
    }

    public function attendanceForUpdate(string $id): ?Attendance
    {
        return $this->row($id, true);
    }

    public function insertAttendance(Attendance $attendance): void
    {
        $this->connection->insert(self::TABLE, ['id' => $attendance->id] + $this->columns($attendance), ['identity_verified' => ParameterType::BOOLEAN]);
    }

    public function updateAttendance(Attendance $attendance): void
    {
        $changed = $this->connection->update(self::TABLE, $this->columns($attendance), ['id' => $attendance->id, 'version' => $attendance->version - 1], ['identity_verified' => ParameterType::BOOLEAN]);
        if (1 !== $changed) {
            throw ChildrenException::conflict('Record changed. Reload and try again.');
        }
    }

    public function insertAttendanceSegment(AttendanceSegment $segment): void
    {
        $this->connection->insert('children_attendance_segments', [
            'id' => $segment->id,
            'attendance_id' => $segment->attendanceId,
            'session_id' => $segment->sessionId,
            'enrolment_id' => $segment->enrolmentId,
            'assignment_id' => $segment->assignmentId,
            'consent_id' => $segment->consentId,
            'arrived_at' => Timestamps::toDatabase($segment->arrivedAt),
            'left_at' => null === $segment->leftAt ? null : Timestamps::toDatabase($segment->leftAt),
        ]);
    }

    public function insertAttendanceEvent(AttendanceEvent $event): void
    {
        $this->connection->insert('children_attendance_events', [
            'id' => $event->id,
            'attendance_id' => $event->attendanceId,
            'actor_id' => $event->actorId,
            'type' => $event->type,
            'reason' => $event->reason,
            'occurred_at' => Timestamps::toDatabase($event->occurredAt),
        ]);
    }

    public function closeSegment(string $attendanceId, DateTimeImmutable $at): void
    {
        $this->connection->executeStatement(
            'UPDATE children_attendance_segments SET left_at = :at WHERE attendance_id = :attendance AND left_at IS NULL',
            ['at' => Timestamps::toDatabase($at), 'attendance' => $attendanceId],
        );
    }

    public function roster(string $sessionId, bool $openOnly, int $limit, int $offset): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT * FROM children_attendance WHERE session_id = :session'.($openOnly ? ' AND checked_out_at IS NULL' : '').' ORDER BY arrived_at, id LIMIT :limit OFFSET :offset',
            ['session' => $sessionId, 'limit' => $limit, 'offset' => $offset],
        );

        return array_map(self::attendanceFrom(...), $rows);
    }

    public function childAttendance(string $childId, int $limit, int $offset): array
    {
        $rows = $this->connection->fetchAllAssociative('SELECT * FROM children_attendance WHERE child_id = :child ORDER BY arrived_at DESC, id LIMIT :limit OFFSET :offset', ['child' => $childId, 'limit' => $limit, 'offset' => $offset]);

        return array_map(self::attendanceFrom(...), $rows);
    }

    public function occupancy(string $sessionId): int
    {
        return (int) $this->connection->fetchOne('SELECT COUNT(*) FROM children_attendance WHERE session_id = :session AND checked_out_at IS NULL', ['session' => $sessionId]);
    }

    public function checkedIn(string $childId, string $sessionId): bool
    {
        return false !== $this->connection->fetchOne('SELECT 1 FROM children_attendance WHERE child_id = :child AND session_id = :session AND checked_out_at IS NULL LIMIT 1', ['child' => $childId, 'session' => $sessionId]);
    }

    public function arrival(string $actorId, string $requestId): ?Attendance
    {
        $row = $this->connection->fetchAssociative('SELECT * FROM children_attendance WHERE arrived_by = :actor AND arrival_request_id = :request', ['actor' => $actorId, 'request' => $requestId]);

        return false === $row ? null : self::attendanceFrom($row);
    }

    public function events(string $attendanceId, int $limit, int $offset): array
    {
        $rows = $this->connection->fetchAllAssociative('SELECT * FROM children_attendance_events WHERE attendance_id = :attendance ORDER BY occurred_at, id LIMIT :limit OFFSET :offset', ['attendance' => $attendanceId, 'limit' => $limit, 'offset' => $offset]);

        return array_map(static fn (array $row): AttendanceEvent => new AttendanceEvent((string) $row['id'], (string) $row['attendance_id'], (string) $row['actor_id'], (string) $row['type'], (string) $row['reason'], Timestamps::fromDatabase((string) $row['occurred_at'])), $rows);
    }

    public function segments(string $attendanceId): array
    {
        $rows = $this->connection->fetchAllAssociative('SELECT * FROM children_attendance_segments WHERE attendance_id = :attendance ORDER BY arrived_at, id', ['attendance' => $attendanceId]);

        return array_map(static fn (array $row): AttendanceSegment => new AttendanceSegment(
            (string) $row['id'],
            (string) $row['attendance_id'],
            (string) $row['session_id'],
            (string) $row['enrolment_id'],
            (string) $row['assignment_id'],
            (string) $row['consent_id'],
            Timestamps::fromDatabase((string) $row['arrived_at']),
            null === $row['left_at'] ? null : Timestamps::fromDatabase((string) $row['left_at']),
        ), $rows);
    }

    private function row(string $id, bool $lock): ?Attendance
    {
        $row = $this->connection->fetchAssociative('SELECT * FROM children_attendance WHERE id = :id'.($lock ? ' FOR UPDATE' : ''), ['id' => $id]);

        return false === $row ? null : self::attendanceFrom($row);
    }

    /** @param array<string, mixed> $row */
    public static function attendanceFrom(array $row): Attendance
    {
        $time = static fn (mixed $value): ?DateTimeImmutable => null === $value ? null : Timestamps::fromDatabase((string) $value);
        $text = static fn (mixed $value): ?string => null === $value ? null : (string) $value;

        return Attendance::reconstitute(
            (string) $row['id'],
            (string) $row['child_id'],
            (string) $row['session_id'],
            Timestamps::fromDatabase((string) $row['arrived_at']),
            (string) $row['arrived_by'],
            (string) $row['arrival_request_id'],
            $time($row['checked_out_at']),
            $text($row['checked_out_by']),
            $text($row['collector_id']),
            $text($row['checkout_request_id']),
            $text($row['authorization_id']),
            (bool) $row['identity_verified'],
            $text($row['exception_reason']),
            $text($row['label_hash']),
            (int) $row['label_version'],
            $time($row['label_expires_at']),
            (int) $row['failed_attempts'],
            $time($row['attempts_reset_at']),
            (int) $row['version'],
        );
    }

    /** @return array<string, mixed> */
    private function columns(Attendance $attendance): array
    {
        $time = static fn (?DateTimeImmutable $value): ?string => null === $value ? null : Timestamps::toDatabase($value);

        return [
            'child_id' => $attendance->childId,
            'session_id' => $attendance->sessionId,
            'arrived_at' => Timestamps::toDatabase($attendance->arrivedAt),
            'arrived_by' => $attendance->arrivedBy,
            'arrival_request_id' => $attendance->arrivalRequestId,
            'checked_out_at' => $time($attendance->checkedOutAt),
            'checked_out_by' => $attendance->checkedOutBy,
            'collector_id' => $attendance->collectorId,
            'checkout_request_id' => $attendance->checkoutRequestId,
            'authorization_id' => $attendance->authorizationId,
            'identity_verified' => $attendance->identityVerified,
            'exception_reason' => $attendance->exceptionReason,
            'label_hash' => $attendance->labelHash,
            'label_version' => $attendance->labelVersion,
            'label_expires_at' => $time($attendance->labelExpiresAt),
            'failed_attempts' => $attendance->failedAttempts,
            'attempts_reset_at' => $time($attendance->attemptsResetAt),
            'version' => $attendance->version,
        ];
    }
}
