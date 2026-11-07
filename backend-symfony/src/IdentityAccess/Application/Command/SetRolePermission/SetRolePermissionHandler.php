<?php

declare(strict_types=1);

namespace App\IdentityAccess\Application\Command\SetRolePermission;

use App\IdentityAccess\Application\Authorization\Authorization;
use App\IdentityAccess\Application\Contract\AccessReader;
use App\IdentityAccess\Application\Contract\AccessWriter;
use App\IdentityAccess\Application\Contract\IdentityAuditWriter;
use App\IdentityAccess\Application\Contract\PermissionSummary;
use App\IdentityAccess\Application\Contract\RoleSummary;
use App\IdentityAccess\Domain\AccountManagementPolicy;
use App\IdentityAccess\Domain\IdentityException;
use App\Shared\Application\CommandHandler;
use Psr\Clock\ClockInterface;

final readonly class SetRolePermissionHandler implements CommandHandler
{
    public function __construct(
        private Authorization $authorization,
        private AccessReader $access,
        private AccessWriter $writer,
        private IdentityAuditWriter $audit,
        private ClockInterface $clock,
    ) {
    }

    public function handle(SetRolePermissionCommand $command): void
    {
        $this->writer->lockAccessChanges();
        $this->authorization->requireActorPermission($command->actorId, 'identity.manage');

        $roleExists = array_any(
            $this->access->roles(),
            static fn (RoleSummary $role): bool => $role->id === $command->roleId,
        );
        $permissionExists = array_any(
            $this->access->permissions(),
            static fn (PermissionSummary $permission): bool => $permission->id === $command->permissionId,
        );
        if (!$roleExists || !$permissionExists) {
            throw IdentityException::notFound('Role or permission not found.');
        }

        $this->writer->assignPermission($command->roleId, $command->permissionId, $command->assign);
        AccountManagementPolicy::requireActiveManager($this->access->activeManagers());
        $this->audit->audit(
            $command->actorId,
            'rolepermission.'.($command->assign ? 'assigned' : 'removed'),
            $command->roleId,
            $this->clock->now(),
        );
    }
}
