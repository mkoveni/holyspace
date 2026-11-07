<?php

declare(strict_types=1);

namespace App\IdentityAccess\Application\Command\SetAccountStatus;

use App\IdentityAccess\Application\Authorization\Authorization;
use App\IdentityAccess\Application\Contract\AccessReader;
use App\IdentityAccess\Application\Contract\AccessWriter;
use App\IdentityAccess\Application\Contract\IdentityAuditWriter;
use App\IdentityAccess\Application\Contract\SessionWriter;
use App\IdentityAccess\Domain\AccountManagementPolicy;
use App\IdentityAccess\Domain\IdentityException;
use App\IdentityAccess\Domain\Repository\AccountRepository;
use App\Shared\Application\CommandHandler;
use Psr\Clock\ClockInterface;

final readonly class SetAccountStatusHandler implements CommandHandler
{
    public function __construct(
        private Authorization $authorization,
        private AccountRepository $repository,
        private AccessReader $access,
        private AccessWriter $writer,
        private IdentityAuditWriter $audit,
        private SessionWriter $sessions,
        private ClockInterface $clock,
    ) {
    }

    public function handle(SetAccountStatusCommand $command): void
    {
        $this->writer->lockAccessChanges();
        $this->authorization->requireActorPermission($command->actorId, 'identity.manage');

        $account = $this->repository->findForUpdate($command->id)
            ?? throw IdentityException::notFound('Account not found.');
        $now = $this->clock->now();
        $this->repository->save($command->enabled ? $account->enable($command->expectedVersion, $now) : $account->disable($command->expectedVersion, $now));
        AccountManagementPolicy::requireActiveManager($this->access->activeManagers());

        if (!$command->enabled) {
            $this->sessions->revokeAll($command->id);
        }
        $this->audit->audit(
            $command->actorId,
            $command->enabled ? 'account.enabled' : 'account.disabled',
            $command->id,
            $now,
        );
    }
}
