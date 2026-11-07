<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Sessions\Application\Contract;

final readonly class VolunteerStaffLink
{
    public function __construct(public string $assignmentId, public string $role)
    {
    }
}
