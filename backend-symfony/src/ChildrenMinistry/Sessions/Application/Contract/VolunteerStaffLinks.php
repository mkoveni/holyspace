<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Sessions\Application\Contract;

/** Links a session worker to the volunteer assignment that serves them. */
interface VolunteerStaffLinks
{
    public function find(string $sessionId, string $workerId): ?VolunteerStaffLink;
}
