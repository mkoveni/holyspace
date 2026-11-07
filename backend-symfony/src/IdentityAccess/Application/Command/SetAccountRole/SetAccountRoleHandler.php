<?php

declare(strict_types=1);

namespace App\IdentityAccess\Application\Command\SetAccountRole;

use App\IdentityAccess\Application\Authorization\Authorization;
use App\IdentityAccess\Application\Contract\AccessReader;
use App\IdentityAccess\Application\Contract\AccessWriter;
use App\IdentityAccess\Application\Contract\AccountReader;
use App\IdentityAccess\Application\Contract\IdentityAuditWriter;
use App\IdentityAccess\Application\Contract\RoleSummary;
use App\IdentityAccess\Domain\AccountManagementPolicy;
use App\IdentityAccess\Domain\IdentityException;
use App\Shared\Application\CommandHandler;
use Psr\Clock\ClockInterface;

final readonly class SetAccountRoleHandler implements CommandHandler
{
    public function __construct(
        private Authorization $authorization,
        private AccountReader $accounts,
        private AccessReader $access,
        private AccessWriter $writer,
        private IdentityAuditWriter $audit,
        private ClockInterface $clock,
    ) {
    }

    public function handle(SetAccountRoleCommand $command): void
    {
        $this->writer->lockAccessChanges();
        $this->authorization->requireActorPermission($command->actorId, 'identity.manage');

        $roleExists = array_any(
            $this->access->roles(),
            static fn (RoleSummary $role): bool => $role->id === $command->roleId,
        );
        if (null === $this->accounts->find($command->accountId) || !$roleExists) {
            throw IdentityException::notFound('Account or role not found.');
        }

        $this->writer->assignRole($command->accountId, $command->roleId, $command->assign);
        AccountManagementPolicy::requireActiveManager($this->access->activeManagers());
        $this->audit->audit(
            $command->actorId,
            'accountrole.'.($command->assign ? 'assigned' : 'removed'),
            $command->accountId,
            $this->clock->now(),
        );
    }
}
