<?php

declare(strict_types=1);

namespace App\IdentityAccess\Application\Command\UpdateAccount;

use App\IdentityAccess\Application\Authorization\Authorization;
use App\IdentityAccess\Application\Contract\IdentityAuditWriter;
use App\IdentityAccess\Domain\IdentityException;
use App\IdentityAccess\Domain\IdentityRules;
use App\IdentityAccess\Domain\Repository\AccountRepository;
use App\Shared\Application\CommandHandler;
use Psr\Clock\ClockInterface;

final readonly class UpdateAccountHandler implements CommandHandler
{
    public function __construct(
        private Authorization $authorization,
        private AccountRepository $repository,
        private IdentityAuditWriter $audit,
        private ClockInterface $clock,
    ) {
    }

    public function handle(UpdateAccountCommand $command): void
    {
        $this->authorization->requireActorPermission($command->actorId, 'identity.manage');
        $email = IdentityRules::email($command->email);
        $name = IdentityRules::name($command->displayName);

        $account = $this->repository->findForUpdate($command->id)
            ?? throw IdentityException::notFound('Account not found.');
        $now = $this->clock->now();
        $this->repository->save($account->updateProfile($email, $name, $command->expectedVersion, $now));
        $this->audit->audit($command->actorId, 'account.updated', $command->id, $now);
    }
}
