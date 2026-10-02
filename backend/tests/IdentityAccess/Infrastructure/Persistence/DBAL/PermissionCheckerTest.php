<?php

declare(strict_types=1);

namespace App\Tests\IdentityAccess\Infrastructure\Persistence\DBAL;

use App\IdentityAccess\Application\Service\RolePermissionChecker;
use App\IdentityAccess\Domain\Model\Permission;
use App\IdentityAccess\Domain\Model\Role;
use App\IdentityAccess\Domain\Model\UserAccount;
use App\IdentityAccess\Domain\Repository\PermissionRepository;
use App\IdentityAccess\Domain\Repository\RoleRepository;
use App\IdentityAccess\Domain\Repository\UserAccountRepository;
use App\IdentityAccess\Domain\ValueObject\PasswordHash;
use App\IdentityAccess\Domain\ValueObject\PermissionId;
use App\IdentityAccess\Domain\ValueObject\RoleId;
use App\IdentityAccess\Domain\ValueObject\UserId;
use App\IdentityAccess\Domain\ValueObject\Username;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class PermissionCheckerTest extends TestCase
{
    public function testGrantedThroughRole(): void
    {
        $now = new DateTimeImmutable('2026-01-01 10:00:00');
        $user = UserAccount::create(UserId::generate(), Username::fromString('admin'), PasswordHash::fromString('hash'), null, $now);
        $role = Role::create(RoleId::generate(), 'Admin', '', $now);
        $permission = Permission::create(PermissionId::generate(), 'member.view', 'View', $now);
        $user->assignRole($role->id(), $now);
        $role->addPermission($permission->id(), $now);

        $users = $this->createMock(UserAccountRepository::class);
        $roles = $this->createMock(RoleRepository::class);
        $permissions = $this->createMock(PermissionRepository::class);
        $users->method('findById')->willReturn($user);
        $roles->method('findById')->willReturn($role);
        $permissions->method('findByCode')->willReturn($permission);

        self::assertTrue((new RolePermissionChecker($users, $roles, $permissions))->isGranted($user->id(), 'member.view'));
    }
}
