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
        $exists = $this->connection->createQueryBuilder()->select('1')->from('user_accounts')->where('id = :id')->setParameter('id', $user->id()->toString())->setMaxResults(1)->fetchOne();
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
        return $this->hydrate($this->connection->createQueryBuilder()->select('u.*')->from('user_accounts', 'u')->where('u.id = :id')->setParameter('id', $id->toString())->fetchAssociative());
    }

    public function findByUsername(Username $username): ?UserAccount
    {
        return $this->hydrate($this->connection->createQueryBuilder()->select('u.*')->from('user_accounts', 'u')->where('u.username = :username')->setParameter('username', $username->value())->fetchAssociative());
    }

    public function existsByUsername(Username $username): bool
    {
        return $this->connection->createQueryBuilder()->select('1')->from('user_accounts')->where('username = :username')->setParameter('username', $username->value())->setMaxResults(1)->fetchOne() !== false;
    }

    private function hydrate(array|false $row): ?UserAccount
    {
        if ($row === false) return null;
        $roleIds = $this->connection->createQueryBuilder()->select('ur.role_id')->from('user_roles', 'ur')->where('ur.user_id = :id')->setParameter('id', $row['id'])->orderBy('ur.role_id', 'ASC')->fetchFirstColumn();
        return $this->mapper->toDomain($row, array_map(static fn(mixed $id): string => (string) $id, $roleIds));
    }
}
