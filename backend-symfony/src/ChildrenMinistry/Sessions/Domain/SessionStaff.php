<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Sessions\Domain;

/** A worker assigned to a session, and whether they are actually present. */
final readonly class SessionStaff
{
    public function __construct(public string $sessionId, public string $workerId, public StaffRole $role, public bool $present)
    {
    }
}
