<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Reporting\Infrastructure\Persistence;

use App\ChildrenMinistry\Reporting\Application\AttendanceCriteria;
use App\ChildrenMinistry\Reporting\Application\AttendancePage;
use App\ChildrenMinistry\Reporting\Application\Contract\AttendanceReportReader;
use App\ChildrenMinistry\Reporting\Application\ReportSummary;
use App\ChildrenMinistry\Reporting\Application\ReportVisit;
use App\Shared\Infrastructure\Persistence\ConsistentRead;
use App\Shared\Infrastructure\Persistence\Timestamps;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;

/**
 * Reads the attendance segments directly. Only identifiers, names of places and times leave this class: never pickup codes,
 * evidence, collector identity or exception reasons (a supervisor collection is a flag, not a reason).
 */
final readonly class DbalAttendanceReportStore implements AttendanceReportReader
{
    private const string OUTCOME = "CASE WHEN g.left_at IS NULL THEN 'ATTENDING' WHEN EXISTS (SELECT 1 FROM children_attendance_segments n WHERE n.attendance_id = t.id AND n.arrived_at > g.arrived_at) THEN 'TRANSFERRED' ELSE 'COLLECTED' END";

    private const string EXCEPTION = '('.self::OUTCOME." = 'COLLECTED' AND COALESCE(t.exception_reason, '') <> '')";

    private const string SOURCE = 'children_attendance_segments g JOIN children_attendance t ON t.id = g.attendance_id JOIN children_sessions s ON s.id = g.session_id JOIN children_classes c ON c.id = s.class_id JOIN children_programmes p ON p.id = c.programme_id JOIN children_rooms r ON r.id = s.room_id JOIN children_locations l ON l.id = r.location_id';

    public function __construct(private Connection $connection)
    {
    }

    public function read(AttendanceCriteria $criteria, int $limit, int $offset): AttendancePage
    {
        if (null !== $criteria->matchingChildIds && [] === $criteria->matchingChildIds) {
            return new AttendancePage([], new ReportSummary(0, 0, 0, 0, 0, 0));
        }
        [$where, $parameters, $types] = $this->where($criteria);

        return ConsistentRead::run($this->connection, function () use ($where, $parameters, $types, $criteria, $limit, $offset): AttendancePage {
            $row = $this->connection->fetchNumeric(
                'SELECT count(*), count(DISTINCT t.child_id), count(*) FILTER (WHERE '.self::OUTCOME." = 'ATTENDING'), count(*) FILTER (WHERE ".self::OUTCOME." = 'COLLECTED'), count(*) FILTER (WHERE ".self::OUTCOME." = 'TRANSFERRED'), count(*) FILTER (WHERE ".self::EXCEPTION.') FROM '.self::SOURCE.' WHERE '.$where,
                $parameters,
                $types,
            ) ?: [0, 0, 0, 0, 0, 0];
            $rows = $this->connection->fetchAllAssociative(
                'SELECT g.id, t.id AS attendance_id, t.child_id, g.session_id, p.id AS programme_id, p.name AS programme, c.name AS class_name, r.name AS room, l.name AS location, l.time_zone, g.arrived_at, g.left_at, '.self::OUTCOME.' AS outcome, '.self::EXCEPTION.' AS supervisor
                 FROM '.self::SOURCE.' WHERE '.$where.' ORDER BY g.arrived_at '.(null === $criteria->attendanceId ? 'DESC' : 'ASC').', g.id LIMIT :limit OFFSET :offset',
                $parameters + ['limit' => $limit, 'offset' => $offset],
                $types,
            );

            return new AttendancePage(array_map(static fn (array $r): ReportVisit => new ReportVisit(
                (string) $r['id'],
                (string) $r['attendance_id'],
                (string) $r['child_id'],
                '',
                (string) $r['session_id'],
                (string) $r['programme_id'],
                (string) $r['programme'],
                (string) $r['class_name'],
                (string) $r['room'],
                (string) $r['location'],
                (string) $r['time_zone'],
                Timestamps::fromDatabase((string) $r['arrived_at']),
                null === $r['left_at'] ? null : Timestamps::fromDatabase((string) $r['left_at']),
                (string) $r['outcome'],
                (bool) $r['supervisor'],
            ), $rows), new ReportSummary((int) $row[0], (int) $row[1], (int) $row[2], (int) $row[3], (int) $row[4], (int) $row[5]));
        });
    }

    /** @return array{string, array<string, mixed>, array<string, mixed>} */
    private function where(AttendanceCriteria $criteria): array
    {
        $where = 'TRUE';
        $parameters = [];
        $types = [];
        if (null !== $criteria->from && null !== $criteria->until) {
            $where .= ' AND g.arrived_at >= :from AND g.arrived_at < :until';
            $parameters['from'] = Timestamps::toDatabase($criteria->from);
            $parameters['until'] = Timestamps::toDatabase($criteria->until);
        }
        foreach (['programme' => [$criteria->programmeId, 'p.id'], 'location' => [$criteria->locationId, 'l.id'], 'child' => [$criteria->childId, 't.child_id'], 'attendance' => [$criteria->attendanceId, 't.id']] as $name => [$value, $column]) {
            if (null !== $value) {
                $where .= " AND $column = :$name";
                $parameters[$name] = $value;
            }
        }
        if (null !== $criteria->matchingChildIds) {
            $where .= ' AND t.child_id IN (:matching)';
            $parameters['matching'] = $criteria->matchingChildIds;
            $types['matching'] = ArrayParameterType::STRING;
        }
        if ('ALL' !== $criteria->outcome) {
            $where .= ' AND '.('SUPERVISOR' === $criteria->outcome ? self::EXCEPTION : '('.self::OUTCOME.' = :outcome)');
            if ('SUPERVISOR' !== $criteria->outcome) {
                $parameters['outcome'] = $criteria->outcome;
            }
        }

        return [$where, $parameters, $types];
    }
}
