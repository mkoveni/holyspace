<?php

declare(strict_types=1);

namespace App\IdentityAccess\Application\Command\BootstrapAdministrator;

use App\IdentityAccess\Application\Contract\AccessWriter;
use App\IdentityAccess\Application\Contract\AccountReader;
use App\IdentityAccess\Application\Contract\IdentityAuditWriter;
use App\IdentityAccess\Application\Contract\PasswordHasher;
use App\IdentityAccess\Domain\Account;
use App\IdentityAccess\Domain\IdentityException;
use App\IdentityAccess\Domain\IdentityRules;
use App\IdentityAccess\Domain\Repository\AccountRepository;
use App\Shared\Application\CommandHandler;
use App\Shared\Application\IdGenerator;
use Psr\Clock\ClockInterface;

/** Explicit exception to protected-handler authorization: only allowed before the first account exists. */
final readonly class BootstrapAdministratorHandler implements CommandHandler
{
    private const string ADMINISTRATOR_ROLE_ID = '00000000-0000-0000-0000-000000000001';

    public function __construct(
        private AccountReader $reader,
        private AccessWriter $writer,
        private IdentityAuditWriter $audit,
        private AccountRepository $repository,
        private PasswordHasher $passwords,
        private ClockInterface $clock,
        private IdGenerator $ids,
    ) {
    }

    /** @return string the administrator's account id */
    public function handle(BootstrapAdministratorCommand $command): string
    {
        $username = IdentityRules::username($command->username);
        $email = IdentityRules::email($command->email);
        $name = IdentityRules::name($command->displayName);
        IdentityRules::password($command->password);
        $hash = $this->passwords->hash($command->password);

        $this->writer->lockAccessChanges();
        if ($this->reader->any()) {
            throw IdentityException::conflict('Administrator bootstrap is only allowed before the first account exists.');
        }

        $id = $this->ids->uuid();
        $now = $this->clock->now();
        $this->repository->add(Account::register($id, $username, $email, $name, $hash, $now));
        $this->writer->assignRole($id, self::ADMINISTRATOR_ROLE_ID, true);
        $this->audit->audit(null, 'administrator.bootstrapped', $id, $now);

        return $id;
    }
}
