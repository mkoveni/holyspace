<?php

declare(strict_types=1);

namespace App\IdentityAccess\Infrastructure\Console;

use App\IdentityAccess\Domain\Service\PasswordHasher;
use App\IdentityAccess\Domain\ValueObject\Username;
use Doctrine\DBAL\Connection;
use DomainException;
use InvalidArgumentException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Uid\Uuid;

#[AsCommand(name: 'app:accounts:bootstrap', description: 'Create the first administrator from a private password file; refuses databases with existing accounts.')]
final class BootstrapAdministratorCommand extends Command
{
    public function __construct(private readonly Connection $db, private readonly PasswordHasher $passwords, private readonly LockFactory $locks)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('username', null, InputOption::VALUE_REQUIRED, 'Initial administrator username');
        $this->addOption('password-file', null, InputOption::VALUE_REQUIRED, 'Readable private file containing the password');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $lock = $this->locks->createLock('onechurch-administrator-bootstrap', 60);
        $lock->acquire(true);
        try {
            $username = Username::fromString((string) $input->getOption('username'))->value();
            $path = (string) $input->getOption('password-file');
            if (!is_file($path) || !is_readable($path) || filesize($path) > 4098) {
                throw new InvalidArgumentException('Provide a readable private password file.');
            }
            $password = rtrim(file_get_contents($path), "\r\n");
            if (strlen($password) < 12 || strlen($password) > 4096) {
                throw new InvalidArgumentException('Password must contain 12–4096 characters.');
            }
            $this->db->transactional(function () use ($username, $password, $lock): void {
                if (0 !== (int) $this->db->fetchOne('SELECT COUNT(*) FROM user_accounts')) {
                    throw new DomainException('Accounts already exist. Use an authorised administrator to manage access.');
                }
                $permissions = $this->db->fetchAllAssociative('SELECT id, code FROM permissions');
                $required = ['identity.manage', 'people.manage', 'families.manage', 'events.manage', 'life_events.manage'];
                if ([] !== array_diff($required, array_column($permissions, 'code'))) {
                    throw new DomainException('Apply all migrations before bootstrapping an administrator.');
                }
                $role = $this->db->fetchOne("SELECT id FROM roles WHERE name = 'administrator'");
                $stamp = gmdate('Y-m-d H:i:s');
                if (false === $role) {
                    $role = Uuid::v7()->toRfc4122();
                    $this->db->insert('roles', ['id' => $role, 'name' => 'administrator', 'description' => 'Initial church administrator', 'created_at' => $stamp, 'updated_at' => $stamp]);
                }
                foreach ($permissions as $permission) {
                    if (!$this->db->fetchOne('SELECT COUNT(*) FROM role_permissions WHERE role_id = ? AND permission_id = ?', [$role, $permission['id']])) {
                        $this->db->insert('role_permissions', ['role_id' => $role, 'permission_id' => $permission['id']]);
                    }
                }
                $user = Uuid::v7()->toRfc4122();
                $this->db->insert('user_accounts', ['id' => $user, 'username' => $username, 'password_hash' => $this->passwords->hash($password)->value(), 'person_id' => null, 'status' => 'active', 'created_at' => $stamp, 'updated_at' => $stamp]);
                $this->db->insert('user_roles', ['user_id' => $user, 'role_id' => $role]);
                if (!$lock->isAcquired() || $lock->isExpired()) {
                    throw new DomainException('Bootstrap lock was lost; no changes committed.');
                }
            });
            $output->writeln('Initial administrator created. Remove the temporary password file and use the account-management screens for later changes.');

            return Command::SUCCESS;
        } catch (InvalidArgumentException|DomainException $error) {
            $output->writeln($error->getMessage());

            return Command::FAILURE;
        } finally {
            $lock->release();
        }
    }
}
