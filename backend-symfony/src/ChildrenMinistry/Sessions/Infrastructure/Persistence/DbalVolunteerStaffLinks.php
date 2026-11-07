<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Sessions\Infrastructure\Persistence;

use App\ChildrenMinistry\Sessions\Application\Contract\VolunteerStaffLink;
use App\ChildrenMinistry\Sessions\Application\Contract\VolunteerStaffLinks;
use App\ChildrenMinistry\Sessions\Application\Contract\VolunteerStaffLinkWriter;
use Doctrine\DBAL\Connection;

/** Links between session workers and the volunteer assignments that serve them. */
final readonly class DbalVolunteerStaffLinks implements VolunteerStaffLinks, VolunteerStaffLinkWriter
{
    public function __construct(private Connection $connection)
    {
    }

    public function find(string $sessionId, string $workerId): ?VolunteerStaffLink
    {
        $row = $this->connection->fetchAssociative('SELECT assignment_id, role FROM children_volunteer_staff_links WHERE session_id = :session AND worker_id = :worker', ['session' => $sessionId, 'worker' => $workerId]);

        return false === $row ? null : new VolunteerStaffLink((string) $row['assignment_id'], (string) $row['role']);
    }

    public function save(string $sessionId, string $workerId, string $assignmentId, string $role): void
    {
        $this->connection->executeStatement(
            'INSERT INTO children_volunteer_staff_links (session_id, worker_id, assignment_id, role) VALUES (:session, :worker, :assignment, :role) ON CONFLICT (session_id, worker_id) DO UPDATE SET assignment_id = EXCLUDED.assignment_id, role = EXCLUDED.role',
            ['session' => $sessionId, 'worker' => $workerId, 'assignment' => $assignmentId, 'role' => $role],
        );
    }
}
