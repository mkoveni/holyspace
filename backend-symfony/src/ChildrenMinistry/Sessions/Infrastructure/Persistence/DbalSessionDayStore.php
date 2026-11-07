<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Sessions\Infrastructure\Persistence;

use App\ChildrenMinistry\Sessions\Application\Contract\SessionDayReader;
use App\Shared\Domain\CalendarDate;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;

/** Search-before-paging queries for the station's session day. */
final readonly class DbalSessionDayStore implements SessionDayReader
{
    public function __construct(private Connection $connection)
    {
    }

    public function expected(string $classId, CalendarDate $on, ?array $matchingIds, int $limit, int $offset): array
    {
        $where = 'a.class_id = :class AND a.ended_at IS NULL AND a.valid_from <= :on AND a.valid_until > :on AND e.ended_at IS NULL AND e.valid_from <= :on AND e.valid_until > :on'.(null === $matchingIds ? '' : ' AND a.child_id IN (:ids)');
        $from = 'FROM children_class_assignments a JOIN children_enrolments e ON e.id = a.enrolment_id WHERE '.$where;
        $params = ['class' => $classId, 'on' => $on->iso()] + (null === $matchingIds ? [] : ['ids' => $matchingIds]);
        $types = null === $matchingIds ? [] : ['ids' => ArrayParameterType::STRING];

        $ids = $this->connection->fetchFirstColumn('SELECT DISTINCT a.child_id '.$from.' ORDER BY a.child_id LIMIT :limit OFFSET :offset', $params + ['limit' => $limit, 'offset' => $offset], $types);
        $total = (int) $this->connection->fetchOne('SELECT COUNT(DISTINCT a.child_id) '.$from, $params, $types);

        return ['childIds' => array_map('strval', $ids), 'total' => $total];
    }

    public function attendance(string $sessionId, bool $collected, ?array $matchingIds, int $limit, int $offset): array
    {
        $where = 'session_id = :session AND checked_out_at IS '.($collected ? 'NOT NULL' : 'NULL').(null === $matchingIds ? '' : ' AND child_id IN (:ids)');
        $params = ['session' => $sessionId] + (null === $matchingIds ? [] : ['ids' => $matchingIds]);
        $types = null === $matchingIds ? [] : ['ids' => ArrayParameterType::STRING];

        $total = (int) $this->connection->fetchOne('SELECT COUNT(*) FROM children_attendance WHERE '.$where, $params, $types);
        $rows = $this->connection->fetchAllAssociative('SELECT * FROM children_attendance WHERE '.$where.' ORDER BY arrived_at DESC, id LIMIT :limit OFFSET :offset', $params + ['limit' => $limit, 'offset' => $offset], $types);

        return ['attendance' => array_map(DbalAttendanceStore::attendanceFrom(...), $rows), 'total' => $total];
    }

    public function current(string $sessionId, array $childIds): array
    {
        if ([] === $childIds) {
            return [];
        }
        $rows = $this->connection->fetchAllAssociative(
            'SELECT * FROM children_attendance WHERE session_id = :session AND child_id IN (:ids) AND checked_out_at IS NULL',
            ['session' => $sessionId, 'ids' => $childIds],
            ['ids' => ArrayParameterType::STRING],
        );
        $found = [];
        foreach ($rows as $row) {
            $found[(string) $row['child_id']] = DbalAttendanceStore::attendanceFrom($row);
        }

        return $found;
    }
}
