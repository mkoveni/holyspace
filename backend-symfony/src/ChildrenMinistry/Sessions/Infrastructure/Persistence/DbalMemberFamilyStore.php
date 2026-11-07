<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Sessions\Infrastructure\Persistence;

use App\ChildrenMinistry\Sessions\Application\Contract\MemberFamilyReader;
use App\ChildrenMinistry\Sessions\Application\FamilyAttendance;
use App\ChildrenMinistry\Sessions\Application\FamilyEnrolment;
use App\ChildrenMinistry\Sessions\Application\FamilySessionRow;
use App\Shared\Domain\CalendarDate;
use App\Shared\Infrastructure\Persistence\Timestamps;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;

final readonly class DbalMemberFamilyStore implements MemberFamilyReader
{
    /** An unrevoked authority of the guardian that spans the instant (half-open). */
    private const string CURRENT = 'a.guardian_id = :guardian AND a.revoked_at IS NULL AND a.valid_from <= :at AND a.valid_until > :at';

    private const string VENUE_JOINS = 'JOIN children_classes c ON c.id = s.class_id JOIN children_programmes p ON p.id = c.programme_id JOIN children_rooms r ON r.id = s.room_id JOIN children_locations l ON l.id = r.location_id';

    public function __construct(private Connection $connection)
    {
    }

    public function children(string $guardianId, DateTimeImmutable $at, int $limit, int $offset): array
    {
        /** @var list<string> $ids */
        $ids = $this->connection->fetchFirstColumn(
            'SELECT DISTINCT a.child_id FROM children_guardian_authorities a WHERE '.self::CURRENT.' ORDER BY a.child_id LIMIT :limit OFFSET :offset',
            ['guardian' => $guardianId, 'at' => Timestamps::toDatabase($at), 'limit' => $limit, 'offset' => $offset],
        );

        return array_map(strval(...), $ids);
    }

    public function authorized(string $guardianId, string $childId, DateTimeImmutable $at): bool
    {
        return false !== $this->connection->fetchOne(
            'SELECT 1 FROM children_guardian_authorities a WHERE '.self::CURRENT.' AND a.child_id = :child LIMIT 1',
            ['guardian' => $guardianId, 'at' => Timestamps::toDatabase($at), 'child' => $childId],
        );
    }

    public function programmes(string $childId, CalendarDate $on, int $limit, int $offset): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT p.id, p.name, e.valid_from, e.valid_until FROM children_enrolments e JOIN children_programmes p ON p.id = e.programme_id
             WHERE e.child_id = :child AND e.ended_at IS NULL AND e.valid_until > :on ORDER BY e.valid_from, e.id LIMIT :limit OFFSET :offset',
            ['child' => $childId, 'on' => $on->iso(), 'limit' => $limit, 'offset' => $offset],
        );

        return array_map(static fn (array $row): FamilyEnrolment => new FamilyEnrolment((string) $row['id'], (string) $row['name'], CalendarDate::parse((string) $row['valid_from']), CalendarDate::parse((string) $row['valid_until'])), $rows);
    }

    public function sessions(string $childId, DateTimeImmutable $from, DateTimeImmutable $until, int $limit, int $offset): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT s.*, p.name AS programme_name, c.name AS class_name, r.name AS room_name, l.name AS location_name, l.time_zone
             FROM children_sessions s JOIN children_member_session_dates d ON d.session_id = s.id '.self::VENUE_JOINS.'
             WHERE s.ends_at > :from AND s.starts_at < :until AND EXISTS (
                 SELECT 1 FROM children_class_assignments ca JOIN children_enrolments e ON e.id = ca.enrolment_id
                 WHERE ca.child_id = :child AND ca.class_id = s.class_id AND ca.ended_at IS NULL AND e.ended_at IS NULL
                   AND ca.valid_from <= d.on_date AND ca.valid_until > d.on_date AND e.valid_from <= d.on_date AND e.valid_until > d.on_date)
             ORDER BY s.starts_at, s.id LIMIT :limit OFFSET :offset',
            ['child' => $childId, 'from' => Timestamps::toDatabase($from), 'until' => Timestamps::toDatabase($until), 'limit' => $limit, 'offset' => $offset],
        );

        return array_map(static fn (array $row): FamilySessionRow => new FamilySessionRow(DbalSessionStore::session($row), (string) $row['programme_name'], (string) $row['class_name'], (string) $row['room_name'], (string) $row['location_name'], (string) $row['time_zone']), $rows);
    }

    public function attendance(string $guardianId, string $childId, DateTimeImmutable $at, int $limit, int $offset): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT t.id, t.session_id, t.arrived_at, t.checked_out_at, t.version, t.label_hash, p.name AS programme_name, c.name AS class_name, r.name AS room_name, l.name AS location_name, l.time_zone
             FROM children_attendance t JOIN children_sessions s ON s.id = t.session_id '.self::VENUE_JOINS.'
             WHERE t.child_id = :child AND t.arrived_at >= (SELECT min(a.valid_from) FROM children_guardian_authorities a WHERE '.self::CURRENT.' AND a.child_id = :child)
             ORDER BY t.arrived_at DESC, t.id LIMIT :limit OFFSET :offset',
            ['guardian' => $guardianId, 'at' => Timestamps::toDatabase($at), 'child' => $childId, 'limit' => $limit, 'offset' => $offset],
        );

        return array_map(static fn (array $row): FamilyAttendance => new FamilyAttendance(
            (string) $row['id'],
            (string) $row['session_id'],
            (string) $row['programme_name'],
            (string) $row['class_name'],
            (string) $row['room_name'],
            (string) $row['location_name'],
            (string) $row['time_zone'],
            Timestamps::fromDatabase((string) $row['arrived_at']),
            null === $row['checked_out_at'] ? null : Timestamps::fromDatabase((string) $row['checked_out_at']),
            (int) $row['version'],
            null !== $row['label_hash'],
        ), $rows);
    }
}
