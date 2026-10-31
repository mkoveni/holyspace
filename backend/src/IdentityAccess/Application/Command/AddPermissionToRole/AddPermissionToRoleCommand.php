<?php

declare(strict_types=1);

namespace App\IdentityAccess\Application\Command\AddPermissionToRole;

use App\Shared\Application\CQRS\Command;

final readonly class AddPermissionToRoleCommand implements Command
{
    public function __construct(public string $roleId, public string $permissionId)
    {
    }
}
