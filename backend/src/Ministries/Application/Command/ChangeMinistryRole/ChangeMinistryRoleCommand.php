<?php

declare(strict_types=1);

namespace App\Ministries\Application\Command\ChangeMinistryRole;

use App\Shared\Application\CQRS\Command;

final readonly class ChangeMinistryRoleCommand implements Command
{
    public function __construct(public string $id, public string $role)
    {
    }
}
