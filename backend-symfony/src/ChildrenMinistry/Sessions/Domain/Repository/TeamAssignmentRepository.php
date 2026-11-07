<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Sessions\Domain\Repository;

/** Removes a worker's assignment to a session. Adding one is {@see SessionRepository::saveSessionStaff()}. */
interface TeamAssignmentRepository
{
    public function removeAssignment(string $sessionId, string $workerId): void;
}
