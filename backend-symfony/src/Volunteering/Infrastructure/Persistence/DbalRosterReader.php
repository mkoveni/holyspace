<?php

declare(strict_types=1);

namespace App\Volunteering\Infrastructure\Persistence;

use App\Shared\Infrastructure\Persistence\Timestamps;
use App\Volunteering\Application\Contract\AssignmentView;
use App\Volunteering\Application\Contract\RosterReader;
use App\Volunteering\Application\Contract\ShiftView;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;

final readonly class DbalRosterReader implements RosterReader
{
    private const SHIFT_COLUMNS = ['id', 'team_id', 'role_id', 'source_kind', 'source_id', 'source_revision', 'starts_at', 'ends_at', 'status', 'positions'];

    public function __construct(private Connection $connection)
    {
    }

    public function assignment(string $id): ?AssignmentView
    {
        $row = $this->connection->createQueryBuilder()
            ->select('id', 'shift_id', 'volunteer_id', 'status')
            ->from('serving_assignments')
            ->where('id = :id')
            ->setParameter('id', $id)
            ->fetchAssociative();

        return false === $row ? null : self::assignmentView($row);
    }

    public function shift(string $id): ?ShiftView
    {
        $row = $this->connection->createQueryBuilder()
            ->select(...self::SHIFT_COLUMNS)
            ->from('serving_shifts')
            ->where('id = :id')
            ->setParameter('id', $id)
            ->fetchAssociative();

        return false === $row ? null : self::shiftView($row);
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

    public function assignments(string $shiftId): array
    {
        $rows = $this->connection->createQueryBuilder()
            ->select('id', 'shift_id', 'volunteer_id', 'status')
            ->from('serving_assignments')
            ->where('shift_id = :shift')
            ->setParameter('shift', $shiftId)
            ->orderBy("status = 'CANCELLED'")
            ->addOrderBy('created_at', 'DESC')
            ->addOrderBy('id')
            ->setMaxResults(1000)
            ->fetchAllAssociative();

        return array_values(array_map(self::assignmentView(...), $rows));
    }

    public function volunteerAssignments(string $volunteerId, DateTimeImmutable $from, DateTimeImmutable $until): array
    {
        $rows = $this->connection->createQueryBuilder()
            ->select('a.id', 'a.shift_id', 'a.volunteer_id', 'a.status')
            ->from('serving_assignments', 'a')
            ->join('a', 'serving_shifts', 's', 's.id = a.shift_id')
            ->where('a.volunteer_id = :volunteer')
            ->andWhere('s.starts_at < :until')
            ->andWhere('s.ends_at > :from')
            ->andWhere("a.status <> 'CANCELLED'")
            ->andWhere("s.status <> 'CANCELLED'")
            ->setParameter('volunteer', $volunteerId)
            ->setParameter('until', Timestamps::toDatabase($until))
            ->setParameter('from', Timestamps::toDatabase($from))
            ->orderBy('s.starts_at')
            ->addOrderBy('a.id')
            ->setMaxResults(1001)
            ->fetchAllAssociative();

        return array_values(array_map(self::assignmentView(...), $rows));
    }

    /** @param array<string, mixed> $row */
    private static function assignmentView(array $row): AssignmentView
    {
        return new AssignmentView((string) $row['id'], (string) $row['shift_id'], (string) $row['volunteer_id'], (string) $row['status']);
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
        );
    }
}
