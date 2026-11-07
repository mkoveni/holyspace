<?php

declare(strict_types=1);

namespace App\IdentityAccess\Application\Command\ChangePassword;

use App\IdentityAccess\Application\Authorization\Authorization;
use App\IdentityAccess\Application\Contract\IdentityAuditWriter;
use App\IdentityAccess\Application\Contract\PasswordHasher;
use App\IdentityAccess\Application\Contract\SessionWriter;
use App\IdentityAccess\Domain\IdentityException;
use App\IdentityAccess\Domain\IdentityRules;
use App\IdentityAccess\Domain\Repository\AccountRepository;
use App\Shared\Application\CommandHandler;
use Psr\Clock\ClockInterface;

final readonly class ChangePasswordHandler implements CommandHandler
{
    public function __construct(
        private Authorization $authorization,
        private AccountRepository $repository,
        private IdentityAuditWriter $audit,
        private SessionWriter $sessions,
        private PasswordHasher $passwords,
        private ClockInterface $clock,
    ) {
    }

    public function handle(ChangePasswordCommand $command): void
    {
        if ($command->administratorReset) {
            $this->authorization->requireActorPermission($command->actorId, 'identity.manage');
        } else {
            $this->authorization->requireSelf($command->actorId);
        }
        IdentityRules::password($command->password);

        $account = $this->repository->findForUpdate($command->id)
            ?? throw IdentityException::notFound('Account not found.');

        if (!$command->administratorReset && ($command->actorId !== $command->id
            || !$this->passwords->matches($command->currentPassword, $account->passwordHash->value))) {
            throw IdentityException::forbidden('Current password is incorrect.');
        }

        $now = $this->clock->now();
        $this->repository->save($account->changePassword($this->passwords->hash($command->password), $command->expectedVersion, $now));
        $this->sessions->revokeAll($command->id);
        $this->audit->audit($command->actorId, 'account.password_changed', $command->id, $now);
    }
}
