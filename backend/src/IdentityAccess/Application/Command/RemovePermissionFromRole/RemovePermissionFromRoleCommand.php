<?php

declare(strict_types=1);

namespace App\IdentityAccess\Application\Command\RemovePermissionFromRole;

use App\Shared\Application\CQRS\Command;

final readonly class RemovePermissionFromRoleCommand implements Command
{
    public function __construct(public string $roleId, public string $permissionId)
    {
    }
}
