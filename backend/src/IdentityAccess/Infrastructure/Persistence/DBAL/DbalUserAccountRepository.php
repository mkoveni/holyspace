<?php

declare(strict_types=1);

namespace App\IdentityAccess\Infrastructure\Persistence\DBAL;

use App\IdentityAccess\Domain\Model\UserAccount;
use App\IdentityAccess\Domain\Repository\UserAccountRepository;
use App\IdentityAccess\Domain\ValueObject\UserId;
use App\IdentityAccess\Domain\ValueObject\Username;
use Doctrine\DBAL\Connection;

final readonly class DbalUserAccountRepository implements UserAccountRepository
{
    public function __construct(
        private Connection $connection,
        private UserAccountMapper $mapper,
    ) {}

    public function save(UserAccount $user): void
    {
        $data = $this->mapper->toRow($user);
        $exists = $this->connection->fetchOne('SELECT 1 FROM user_accounts WHERE id = :id', ['id' => $user->id()->toString()]);
        if ($exists) {
            unset($data['id']);
            $this->connection->update('user_accounts', $data, ['id' => $user->id()->toString()]);
        } else {
            $this->connection->insert('user_accounts', $data);
        }

        $this->connection->delete('user_roles', ['user_id' => $user->id()->toString()]);
        foreach ($user->roleIds() as $roleId) {
            $this->connection->insert('user_roles', [
                'user_id' => $user->id()->toString(),
                'role_id' => $roleId->toString(),
            ]);
        }
    }

    public function findById(UserId $id): ?UserAccount
    {
        return $this->hydrate($this->connection->fetchAssociative(
            'SELECT * FROM user_accounts WHERE id = :id',
            ['id' => $id->toString()],
        ));
    }

    public function findByUsername(Username $username): ?UserAccount
    {
        return $this->hydrate($this->connection->fetchAssociative(
            'SELECT * FROM user_accounts WHERE username = :username',
            ['username' => $username->value()],
        ));
    }

    public function existsByUsername(Username $username): bool
    {
        return (bool) $this->connection->fetchOne(
            'SELECT 1 FROM user_accounts WHERE username = :username',
            ['username' => $username->value()],
        );
    }

    private function hydrate(array|false $row): ?UserAccount
    {
        if ($row === false) return null;
        $roleIds = $this->connection->fetchFirstColumn(
            'SELECT role_id FROM user_roles WHERE user_id = :id ORDER BY role_id',
            ['id' => $row['id']],
        );
        return $this->mapper->toDomain($row, array_map(static fn(mixed $id): string => (string) $id, $roleIds));
    }
}
