<?php

declare(strict_types=1);

namespace App\IdentityAccess\Infrastructure\Persistence\DBAL;

use App\IdentityAccess\Domain\Model\Role;
use App\IdentityAccess\Domain\Repository\RoleRepository;
use App\IdentityAccess\Domain\ValueObject\RoleId;
use App\Shared\Infrastructure\Persistence\AssociationSynchronizer;
use Doctrine\DBAL\Connection;

final readonly class DbalRoleRepository implements RoleRepository
{
    public function __construct(
        private Connection $connection,
        private RoleMapper $mapper,
    ) {
    }

    public function save(Role $role): void
    {
        $this->connection->transactional(function () use ($role): void {
            $data = $this->mapper->toRow($role);
            $exists = $this->connection->createQueryBuilder()->select('1')->from('roles')->where('id = :id')->setParameter('id', $role->id()->toString())->setMaxResults(1)->fetchOne();
            if ($exists) {
                unset($data['id']);
                $this->connection->update('roles', $data, ['id' => $role->id()->toString()]);
            } else {
                $this->connection->insert('roles', $data);
            }

            (new AssociationSynchronizer())->sync($this->connection, 'role_permissions', ['role_id' => $role->id()->toString()], 'permission_id', array_map(static fn ($id) => ['permission_id' => $id->toString()], $role->permissionIds()));
        });
    }

    public function findById(RoleId $id): ?Role
    {
        return $this->hydrate($this->connection->createQueryBuilder()->select('r.*')->from('roles', 'r')->where('r.id = :id')->setParameter('id', $id->toString())->fetchAssociative());
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
        if (false === $row) {
            return null;
        }
        $permissionIds = $this->connection->createQueryBuilder()->select('rp.permission_id')->from('role_permissions', 'rp')->where('rp.role_id = :id')->setParameter('id', $row['id'])->orderBy('rp.permission_id', 'ASC')->fetchFirstColumn();

        return $this->mapper->toDomain($row, array_map(static fn (mixed $id): string => (string) $id, $permissionIds));
    }
}
