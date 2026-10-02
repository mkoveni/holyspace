<?php

declare(strict_types=1);

namespace App\IdentityAccess\Application\Command\AddPermissionToRole;

use App\IdentityAccess\Domain\Exception\RoleNotFound;
use App\IdentityAccess\Domain\Repository\PermissionRepository;
use App\IdentityAccess\Domain\Repository\RoleRepository;
use App\IdentityAccess\Domain\ValueObject\PermissionId;
use App\IdentityAccess\Domain\ValueObject\RoleId;

#[\Symfony\Component\Messenger\Attribute\AsMessageHandler]
final readonly class AddPermissionToRoleHandler
{
    public function __construct(private RoleRepository $roles, private PermissionRepository $permissions) {}
    public function __invoke(AddPermissionToRoleCommand $c): void
    {
        $role = $this->roles->findById(RoleId::fromString($c->roleId));

        if (!$role) throw new RoleNotFound($c->roleId);

        $permissionId = PermissionId::fromString($c->permissionId);
        if (!$this->permissions->findById($permissionId)) throw new \InvalidArgumentException('Permission not found.');
        $role->addPermission($permissionId, new \DateTimeImmutable());
        $this->roles->save($role);
    }
}
