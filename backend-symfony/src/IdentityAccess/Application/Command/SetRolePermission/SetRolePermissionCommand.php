<?php

declare(strict_types=1);

namespace App\IdentityAccess\Application\Command\SetRolePermission;

use App\Shared\Application\Command;

/** @implements Command<null> */
final readonly class SetRolePermissionCommand implements Command
{
    public function __construct(
        public string $actorId,
        public string $roleId,
        public string $permissionId,
        public bool $assign,
    ) {
    }
}
