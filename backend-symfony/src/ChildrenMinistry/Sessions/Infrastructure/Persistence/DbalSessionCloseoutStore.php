<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Sessions\Infrastructure\Persistence;

use App\ChildrenMinistry\Sessions\Application\Contract\SessionCloseoutReader;
use App\ChildrenMinistry\Sessions\Application\SessionCloseout;
use App\Shared\Infrastructure\Persistence\Timestamps;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;

/** One pass over the session's visit segments, so counts do not depend on roster paging. */
final readonly class DbalSessionCloseoutStore implements SessionCloseoutReader
{
    public function __construct(private Connection $connection)
    {
    }

    public function summarize(string $sessionId, DateTimeImmutable $now): SessionCloseout
    {
        $transferred = '(s.left_at IS NOT NULL AND EXISTS (SELECT 1 FROM children_attendance_segments n WHERE n.attendance_id = a.id AND n.arrived_at > s.arrived_at))';
        $collected = '(s.left_at IS NOT NULL AND a.checked_out_at IS NOT NULL AND NOT '.$transferred.')';
        $present = 's.left_at IS NULL';
        $row = $this->connection->fetchNumeric(
            "SELECT COUNT(*), COUNT(DISTINCT a.child_id),
                    COUNT(*) FILTER (WHERE $present),
                    COUNT(*) FILTER (WHERE $collected),
                    COUNT(*) FILTER (WHERE $transferred),
                    COUNT(*) FILTER (WHERE $present AND (a.label_hash IS NULL OR a.label_expires_at <= :now)),
                    COUNT(*) FILTER (WHERE $present AND a.failed_attempts > 0),
                    COUNT(*) FILTER (WHERE $collected AND a.exception_reason <> '')
             FROM children_attendance_segments s JOIN children_attendance a ON a.id = s.attendance_id
             WHERE s.session_id = :session",
            ['session' => $sessionId, 'now' => Timestamps::toDatabase($now)],
        );
        $count = static fn (int $index): int => (int) ($row[$index] ?? 0);

        return new SessionCloseout($count(0), $count(1), $count(2), $count(3), $count(4), $count(5), $count(6), $count(7), $now);
    }
}
