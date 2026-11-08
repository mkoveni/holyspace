<?php

declare(strict_types=1);

namespace App\Volunteering\Infrastructure\Persistence;

use App\Shared\Infrastructure\Persistence\Timestamps;
use App\Volunteering\Application\Contract\AssignmentEvent;
use App\Volunteering\Application\Contract\AssignmentView;
use App\Volunteering\Application\Contract\InvitationRecord;
use App\Volunteering\Application\Contract\RosterDirectory;
use App\Volunteering\Application\Contract\ShiftView;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;

/** Java `JooqRosterStore` reads (`RosterReader`, `MemberDutyReader`). */
final readonly class DbalRosterReader implements RosterDirectory
{
    private const array SHIFT_COLUMNS = ['id', 'request_id', 'request_hash', 'team_id', 'role_id', 'source_kind', 'source_id', 'source_revision', 'starts_at', 'ends_at', 'status', 'positions', 'version', 'schedule_revision'];

    private const array ASSIGNMENT_COLUMNS = ['a.id', 'a.request_id', 'a.request_hash', 'a.shift_id', 'a.volunteer_id', 'a.status', 'a.version', 'a.created_by', 'a.created_at'];

    private const array INVITATION_COLUMNS = ['i.id', 'i.assignment_id', 'i.assignment_version', 'i.shift_version', 'i.expires_at', 'i.status', 'i.version', 'i.responded_at'];

    public function __construct(private Connection $connection)
    {
    }

    public function assignment(string $id): ?AssignmentView
    {
        return $this->oneAssignment('a.id', $id);
    }

    public function assignmentRequest(string $requestId): ?AssignmentView
    {
        return $this->oneAssignment('a.request_id', $requestId);
    }

    public function shift(string $id): ?ShiftView
    {
        return $this->oneShift('id', $id);
    }

    public function shiftRequest(string $requestId): ?ShiftView
    {
        return $this->oneShift('request_id', $requestId);
    }

    public function sourceShifts(string $kind, string $sourceId, int $limit): array
    {
        $rows = $this->connection->createQueryBuilder()
            ->select(...self::SHIFT_COLUMNS)
            ->from('serving_shifts')
            ->where('source_kind = :kind')
            ->andWhere('source_id = :source')
            ->setParameter('kind', $kind)
            ->setParameter('source', $sourceId)
            ->orderBy('starts_at')
            ->addOrderBy('id')
            ->setMaxResults($limit)
            ->fetchAllAssociative();

        return array_values(array_map(self::shiftView(...), $rows));
    }

    public function shifts(DateTimeImmutable $from, DateTimeImmutable $until, ?string $teamId, int $limit, int $offset): array
    {
        $rows = $this->range($this->connection->createQueryBuilder()->select(...self::SHIFT_COLUMNS), $from, $until, $teamId)
            ->orderBy('starts_at')
            ->addOrderBy('id')
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->fetchAllAssociative();

        return array_values(array_map(self::shiftView(...), $rows));
    }

    public function shiftCount(DateTimeImmutable $from, DateTimeImmutable $until, ?string $teamId): int
    {
        return (int) $this->range($this->connection->createQueryBuilder()->select('COUNT(*)'), $from, $until, $teamId)->fetchOne();
    }

    public function assignments(string $shiftId): array
    {
        $rows = $this->connection->createQueryBuilder()
            ->select(...self::ASSIGNMENT_COLUMNS)
            ->from('serving_assignments', 'a')
            ->where('a.shift_id = :shift')
            ->setParameter('shift', $shiftId)
            ->orderBy("a.status = 'CANCELLED'")
            ->addOrderBy('a.created_at', 'DESC')
            ->addOrderBy('a.id')
            ->setMaxResults(1000)
            ->fetchAllAssociative();

        return array_values(array_map(self::assignmentView(...), $rows));
    }

    public function volunteerAssignments(string $volunteerId, DateTimeImmutable $from, DateTimeImmutable $until): array
    {
        $rows = $this->dutyQuery($volunteerId, $from, $until)
            ->andWhere("a.status <> 'CANCELLED'")
            ->andWhere("s.status <> 'CANCELLED'")
            ->setMaxResults(1001)
            ->fetchAllAssociative();

        return array_values(array_map(self::assignmentView(...), $rows));
    }

    public function duties(string $volunteerId, DateTimeImmutable $from, DateTimeImmutable $until, int $limit, int $offset): array
    {
        $rows = $this->dutyQuery($volunteerId, $from, $until)
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->fetchAllAssociative();

        return array_values(array_map(self::assignmentView(...), $rows));
    }

    public function invitation(string $id): ?InvitationRecord
    {
        $row = $this->connection->createQueryBuilder()
            ->select(...self::INVITATION_COLUMNS)
            ->from('serving_invitations', 'i')
            ->where('i.id = :id')
            ->setParameter('id', $id)
            ->fetchAssociative();

        return false === $row ? null : self::invitationRecord($row);
    }

    public function invitations(string $assignmentId): array
    {
        $rows = $this->newestInvitations($this->connection->createQueryBuilder()
            ->select(...self::INVITATION_COLUMNS)
            ->from('serving_invitations', 'i')
            ->where('i.assignment_id = :assignment')
            ->setParameter('assignment', $assignmentId))
            ->setMaxResults(100)
            ->fetchAllAssociative();

        return array_values(array_map(self::invitationRecord(...), $rows));
    }

    public function expirable(DateTimeImmutable $at, int $limit): array
    {
        $rows = $this->connection->createQueryBuilder()
            ->select(...self::INVITATION_COLUMNS)
            ->from('serving_invitations', 'i')
            ->where("i.status = 'PENDING'")
            ->andWhere('i.expires_at <= :at')
            ->setParameter('at', Timestamps::toDatabase($at))
            ->orderBy('i.expires_at')
            ->addOrderBy('i.id')
            ->setMaxResults($limit)
            ->fetchAllAssociative();

        return array_values(array_map(self::invitationRecord(...), $rows));
    }

    public function myInvitations(string $volunteerId, int $limit, int $offset): array
    {
        $rows = $this->newestInvitations($this->connection->createQueryBuilder()
            ->select(...self::INVITATION_COLUMNS)
            ->from('serving_invitations', 'i')
            ->innerJoin('i', 'serving_assignments', 'a', 'a.id = i.assignment_id')
            ->where('a.volunteer_id = :volunteer')
            ->setParameter('volunteer', $volunteerId))
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->fetchAllAssociative();

        return array_values(array_map(self::invitationRecord(...), $rows));
    }

    public function events(string $assignmentId, int $limit, int $offset): array
    {
        $rows = $this->connection->createQueryBuilder()
            ->select('id', 'action', 'reason', 'occurred_at')
            ->from('serving_assignment_events')
            ->where('assignment_id = :assignment')
            ->setParameter('assignment', $assignmentId)
            ->orderBy('occurred_at', 'DESC')
            ->addOrderBy('id')
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->fetchAllAssociative();

        return array_values(array_map(static fn (array $row): AssignmentEvent => new AssignmentEvent(
            (string) $row['id'],
            (string) $row['action'],
            (string) $row['reason'],
            Timestamps::fromDatabase((string) $row['occurred_at']),
        ), $rows));
    }

    private function oneShift(string $column, string $value): ?ShiftView
    {
        $row = $this->connection->createQueryBuilder()
            ->select(...self::SHIFT_COLUMNS)
            ->from('serving_shifts')
            ->where($column.' = :value')
            ->setParameter('value', $value)
            ->fetchAssociative();

        return false === $row ? null : self::shiftView($row);
    }

    private function oneAssignment(string $column, string $value): ?AssignmentView
    {
        $row = $this->connection->createQueryBuilder()
            ->select(...self::ASSIGNMENT_COLUMNS)
            ->from('serving_assignments', 'a')
            ->where($column.' = :value')
            ->setParameter('value', $value)
            ->fetchAssociative();

        return false === $row ? null : self::assignmentView($row);
    }

    /** Shifts overlapping the half-open interval, optionally of one team. */
    private function range(QueryBuilder $query, DateTimeImmutable $from, DateTimeImmutable $until, ?string $teamId): QueryBuilder
    {
        $query->from('serving_shifts')
            ->where('starts_at < :until')
            ->andWhere('ends_at > :from')
            ->setParameter('until', Timestamps::toDatabase($until))
            ->setParameter('from', Timestamps::toDatabase($from));
        if (null !== $teamId) {
            $query->andWhere('team_id = :team')->setParameter('team', $teamId);
        }

        return $query;
    }

    private function dutyQuery(string $volunteerId, DateTimeImmutable $from, DateTimeImmutable $until): QueryBuilder
    {
        return $this->connection->createQueryBuilder()
            ->select(...self::ASSIGNMENT_COLUMNS)
            ->from('serving_assignments', 'a')
            ->innerJoin('a', 'serving_shifts', 's', 's.id = a.shift_id')
            ->where('a.volunteer_id = :volunteer')
            ->andWhere('s.starts_at < :until')
            ->andWhere('s.ends_at > :from')
            ->setParameter('volunteer', $volunteerId)
            ->setParameter('until', Timestamps::toDatabase($until))
            ->setParameter('from', Timestamps::toDatabase($from))
            ->orderBy('s.starts_at')
            ->addOrderBy('a.id');
    }

    private function newestInvitations(QueryBuilder $query): QueryBuilder
    {
        return $query->orderBy("i.status = 'PENDING'", 'DESC')->addOrderBy('i.expires_at', 'DESC')->addOrderBy('i.id');
    }

    /** @param array<string, mixed> $row */
    private static function assignmentView(array $row): AssignmentView
    {
        return new AssignmentView(
            (string) $row['id'],
            (string) $row['shift_id'],
            (string) $row['volunteer_id'],
            (string) $row['status'],
            (string) $row['request_id'],
            (string) $row['request_hash'],
            (int) $row['version'],
            (string) $row['created_by'],
            Timestamps::fromDatabase((string) $row['created_at']),
        );
    }

    /** @param array<string, mixed> $row */
    private static function shiftView(array $row): ShiftView
    {
        return new ShiftView(
            (string) $row['id'],
            (string) $row['team_id'],
            (string) $row['role_id'],
            (string) $row['source_kind'],
            null === $row['source_id'] ? null : (string) $row['source_id'],
            null === $row['source_revision'] ? null : (string) $row['source_revision'],
            Timestamps::fromDatabase((string) $row['starts_at']),
            Timestamps::fromDatabase((string) $row['ends_at']),
            (string) $row['status'],
            (int) $row['positions'],
            (string) $row['request_id'],
            (string) $row['request_hash'],
            (int) $row['version'],
            (int) $row['schedule_revision'],
        );
    }

    /** @param array<string, mixed> $row */
    private static function invitationRecord(array $row): InvitationRecord
    {
        return new InvitationRecord(
            (string) $row['id'],
            (string) $row['assignment_id'],
            (int) $row['assignment_version'],
            (int) $row['shift_version'],
            Timestamps::fromDatabase((string) $row['expires_at']),
            (string) $row['status'],
            (int) $row['version'],
            null === $row['responded_at'] ? null : Timestamps::fromDatabase((string) $row['responded_at']),
        );
    }
}
