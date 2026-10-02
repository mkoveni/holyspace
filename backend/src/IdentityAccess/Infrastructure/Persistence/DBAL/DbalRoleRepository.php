<?php

declare(strict_types=1);

namespace App\IdentityAccess\Infrastructure\Persistence\DBAL;

use App\IdentityAccess\Domain\Model\Role;
use App\IdentityAccess\Domain\Repository\RoleRepository;
use App\IdentityAccess\Domain\ValueObject\RoleId;
use Doctrine\DBAL\Connection;

final readonly class DbalRoleRepository implements RoleRepository
{
    public function __construct(
        private Connection $connection,
        private RoleMapper $mapper,
    ) {}

    public function save(Role $role): void
    {
        $data = $this->mapper->toRow($role);
        $exists = $this->connection->fetchOne('SELECT 1 FROM roles WHERE id = :id', ['id' => $role->id()->toString()]);
        if ($exists) {
            unset($data['id']);
            $this->connection->update('roles', $data, ['id' => $role->id()->toString()]);
        } else {
            $this->connection->insert('roles', $data);
        }

        $this->connection->delete('role_permissions', ['role_id' => $role->id()->toString()]);
        foreach ($role->permissionIds() as $permissionId) {
            $this->connection->insert('role_permissions', [
                'role_id' => $role->id()->toString(),
                'permission_id' => $permissionId->toString(),
            ]);
        }
    }

    public function findById(RoleId $id): ?Role
    {
        return $this->hydrate($this->connection->fetchAssociative(
            'SELECT * FROM roles WHERE id = :id',
            ['id' => $id->toString()],
        ));
    }

    public function findByName(string $name): ?Role
    {
        return $this->hydrate($this->connection->fetchAssociative(
            'SELECT * FROM roles WHERE name = :name',
            ['name' => trim($name)],
        ));
    }

    private function hydrate(array|false $row): ?Role
    {
        if ($row === false) return null;
        $permissionIds = $this->connection->fetchFirstColumn(
            'SELECT permission_id FROM role_permissions WHERE role_id = :id ORDER BY permission_id',
            ['id' => $row['id']],
        );
        return $this->mapper->toDomain($row, array_map(static fn(mixed $id): string => (string) $id, $permissionIds));
    }
}
