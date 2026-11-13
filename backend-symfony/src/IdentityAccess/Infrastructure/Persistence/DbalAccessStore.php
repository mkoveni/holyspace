<?php

declare(strict_types=1);

namespace App\IdentityAccess\Infrastructure\Persistence;

use App\IdentityAccess\Application\Contract\AccessReader;
use App\IdentityAccess\Application\Contract\AccessWriter;
use App\IdentityAccess\Application\Contract\PermissionSummary;
use App\IdentityAccess\Application\Contract\RoleSummary;
use App\Shared\Infrastructure\Persistence\AdvisoryLock;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;

final readonly class DbalAccessStore implements AccessReader, AccessWriter
{
    private const int ACCESS_CHANGE_LOCK = 78219401;

    public function __construct(private Connection $connection, private AdvisoryLock $locks)
    {
    }

    public function roles(): array
    {
        $rows = $this->connection->createQueryBuilder()
            ->select('id', 'name', 'description')->from('roles')->orderBy('name')->fetchAllAssociative();
        $granted = [];
        foreach ($this->connection->createQueryBuilder()
            ->select('rp.role_id', 'p.code')->from('role_permissions', 'rp')
            ->join('rp', 'permissions', 'p', 'p.id = rp.permission_id')
            ->orderBy('p.code')->fetchAllAssociative() as $grant) {
            $granted[(string) $grant['role_id']][] = (string) $grant['code'];
        }

        return array_values(array_map(
            static fn (array $r): RoleSummary => new RoleSummary((string) $r['id'], (string) $r['name'], (string) $r['description'], $granted[(string) $r['id']] ?? []),
            $rows,
        ));
    }

    public function roleNamesByAccount(array $accountIds): array
    {
        if ([] === $accountIds) {
            return [];
        }
        $names = [];
        foreach ($this->connection->createQueryBuilder()
            ->select('ur.user_id', 'r.name')->from('user_roles', 'ur')
            ->join('ur', 'roles', 'r', 'r.id = ur.role_id')
            ->where('ur.user_id IN (:accounts)')->setParameter('accounts', $accountIds, ArrayParameterType::STRING)
            ->orderBy('r.name')->fetchAllAssociative() as $row) {
            $names[(string) $row['user_id']][] = (string) $row['name'];
        }

        return $names;
    }

    public function permissions(): array
    {
        $rows = $this->connection->createQueryBuilder()
            ->select('id', 'code', 'description')->from('permissions')->orderBy('code')->fetchAllAssociative();

        return array_values(array_map(
            static fn (array $r): PermissionSummary => new PermissionSummary((string) $r['id'], (string) $r['code'], (string) $r['description']),
            $rows,
        ));
    }

    public function roleNames(string $accountId): array
    {
        return array_values(array_map('strval', $this->connection->createQueryBuilder()
            ->select('r.name')->from('roles', 'r')
            ->join('r', 'user_roles', 'ur', 'ur.role_id = r.id')
            ->where('ur.user_id = :account')->setParameter('account', $accountId)
            ->orderBy('r.name')->fetchFirstColumn()));
    }

    public function permissionCodes(string $accountId): array
    {
        return array_values(array_map('strval', $this->connection->createQueryBuilder()
            ->select('DISTINCT p.code')->from('permissions', 'p')
            ->join('p', 'role_permissions', 'rp', 'rp.permission_id = p.id')
            ->join('rp', 'user_roles', 'ur', 'ur.role_id = rp.role_id')
            ->where('ur.user_id = :account')->setParameter('account', $accountId)
            ->orderBy('p.code')->fetchFirstColumn()));
    }

    public function activeManagers(): int
    {
        return (int) $this->connection->createQueryBuilder()
            ->select('COUNT(DISTINCT a.id)')->from('user_accounts', 'a')
            ->join('a', 'user_roles', 'ur', 'ur.user_id = a.id')
            ->join('ur', 'role_permissions', 'rp', 'rp.role_id = ur.role_id')
            ->join('rp', 'permissions', 'p', 'p.id = rp.permission_id')
            ->where("a.status = 'active'")->andWhere("p.code = 'identity.manage'")
            ->fetchOne();
    }

    public function lockAccessChanges(): void
    {
        $this->locks->lock(self::ACCESS_CHANGE_LOCK);
    }

    public function createRole(string $id, string $name, string $description): void
    {
        $this->connection->insert('roles', ['id' => $id, 'name' => $name, 'description' => $description]);
    }

    public function createPermission(string $id, string $code, string $description): void
    {
        $this->connection->insert('permissions', ['id' => $id, 'code' => $code, 'description' => $description]);
    }

    public function assignRole(string $accountId, string $roleId, bool $assign): void
    {
        if ($assign) {
            $this->connection->executeStatement(
                'INSERT INTO user_roles (user_id, role_id) VALUES (?, ?) ON CONFLICT DO NOTHING',
                [$accountId, $roleId],
            );

            return;
        }
        $this->connection->delete('user_roles', ['user_id' => $accountId, 'role_id' => $roleId]);
    }

    public function assignPermission(string $roleId, string $permissionId, bool $assign): void
    {
        if ($assign) {
            $this->connection->executeStatement(
                'INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?) ON CONFLICT DO NOTHING',
                [$roleId, $permissionId],
            );

            return;
        }
        $this->connection->delete('role_permissions', ['role_id' => $roleId, 'permission_id' => $permissionId]);
    }
}
