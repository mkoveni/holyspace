<?php

declare(strict_types=1);

namespace App\IdentityAccess\Application\Command\AddPermissionToRole;

final readonly class AddPermissionToRoleCommand
{
    public function __construct(public string $roleId, public string $permissionId) {}
}
