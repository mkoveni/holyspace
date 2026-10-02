<?php

declare(strict_types=1);

namespace App\IdentityAccess\Application\Query\GetUserPermissions;

use App\IdentityAccess\Domain\Repository\PermissionRepository;
use App\IdentityAccess\Domain\Repository\RoleRepository;
use App\IdentityAccess\Domain\Repository\UserAccountRepository;
use App\IdentityAccess\Domain\ValueObject\UserId;

final readonly class GetUserPermissionsHandler
{
    public function __construct(private UserAccountRepository $users, private RoleRepository $roles, private PermissionRepository $permissions) {}
    /** @return list<string> */
    public function __invoke(GetUserPermissionsQuery $query): array
    {
        $user = $this->users->findById(UserId::fromString($query->userId));

        if ($user === null) return [];

        $codes = [];

        foreach ($user->roleIds() as $roleId) foreach ($this->permissions->findForRole($roleId) as $permission) $codes[$permission->code()] = true;

        $result = array_keys($codes);

        sort($result);

        return $result;
    }
}
