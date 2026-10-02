<?php

declare(strict_types=1);
namespace App\IdentityAccess\Application\Service;
use App\IdentityAccess\Domain\Repository\PermissionRepository;
use App\IdentityAccess\Domain\Repository\RoleRepository;
use App\IdentityAccess\Domain\Repository\UserAccountRepository;
use App\IdentityAccess\Domain\ValueObject\UserId;
final readonly class RolePermissionChecker implements PermissionChecke
{
    public function __construct(
        private UserAccountRepository $users,
        private RoleRepository $roles,
        private PermissionRepository $permissions,
    ) {}
    public function isGranted(UserId $userId, string $permissionCode): bool
    {
        $user = $this->users->findById($userId);
        if ($user === null || $user->status()->value !== 'active') return false;
        $permission = $this->permissions->findByCode($permissionCode);
        if ($permission === null) return false;
        foreach ($user->roleIds() as $roleId) {
            $role = $this->roles->findById($roleId);
            if ($role?->hasPermission($permission->id())) return true;
        }
        return false;
    }
}
