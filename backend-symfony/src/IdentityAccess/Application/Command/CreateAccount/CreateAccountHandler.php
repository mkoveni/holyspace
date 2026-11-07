<?php

declare(strict_types=1);

namespace App\IdentityAccess\Application\Command\CreateAccount;

use App\IdentityAccess\Application\Authorization\Authorization;
use App\IdentityAccess\Application\Contract\IdentityAuditWriter;
use App\IdentityAccess\Application\Contract\PasswordHasher;
use App\IdentityAccess\Domain\Account;
use App\IdentityAccess\Domain\IdentityRules;
use App\IdentityAccess\Domain\Repository\AccountRepository;
use App\Shared\Application\CommandHandler;
use App\Shared\Application\IdGenerator;
use Psr\Clock\ClockInterface;

final readonly class CreateAccountHandler implements CommandHandler
{
    public function __construct(
        private Authorization $authorization,
        private IdentityAuditWriter $audit,
        private AccountRepository $repository,
        private PasswordHasher $passwords,
        private ClockInterface $clock,
        private IdGenerator $ids,
    ) {
    }

    /** @return string the new account id */
    public function handle(CreateAccountCommand $command): string
    {
        $this->authorization->requireActorPermission($command->actorId, 'identity.manage');
        $username = IdentityRules::username($command->username);
        $email = IdentityRules::email($command->email);
        $name = IdentityRules::name($command->displayName);
        IdentityRules::password($command->password);
        $hash = $this->passwords->hash($command->password);

        $id = $this->ids->uuid();
        $now = $this->clock->now();
        $this->repository->add(Account::register($id, $username, $email, $name, $hash, $now));
        $this->audit->audit($command->actorId, 'account.created', $id, $now);

        return $id;
    }
}
