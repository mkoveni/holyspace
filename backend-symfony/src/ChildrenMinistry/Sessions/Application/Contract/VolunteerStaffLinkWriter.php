<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Sessions\Application\Contract;

interface VolunteerStaffLinkWriter
{
    /** Links the worker to the assignment for the session, replacing any earlier link. */
    public function save(string $sessionId, string $workerId, string $assignmentId, string $role): void;
}
