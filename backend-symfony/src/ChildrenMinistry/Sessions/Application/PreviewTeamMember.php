<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Sessions\Application;

use App\ChildrenMinistry\Sessions\Domain\StaffRole;

final readonly class PreviewTeamMember
{
    public function __construct(public string $workerId, public string $name, public StaffRole $role)
    {
    }
}
