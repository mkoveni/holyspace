<?php

declare(strict_types=1);

namespace App\IdentityAccess\Application\Command\AssignRole;

use App\Shared\Application\CQRS\Command;

final readonly class AssignRoleCommand implements Command
{
    public function __construct(public string $userId, public string $roleId)
    {
    }
}
