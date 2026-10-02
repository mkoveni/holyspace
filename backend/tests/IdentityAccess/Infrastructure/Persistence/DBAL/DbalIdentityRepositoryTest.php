<?php

declare(strict_types=1);

namespace App\Tests\IdentityAccess\Infrastructure\Persistence\DBAL;

use App\IdentityAccess\Domain\Model\Permission;
use App\IdentityAccess\Domain\Model\Role;
use App\IdentityAccess\Domain\Model\UserAccount;
use App\IdentityAccess\Domain\ValueObject\PasswordHash;
use App\IdentityAccess\Domain\ValueObject\PermissionId;
use App\IdentityAccess\Domain\ValueObject\RoleId;
use App\IdentityAccess\Domain\ValueObject\UserId;
use App\IdentityAccess\Domain\ValueObject\Username;
use App\IdentityAccess\Infrastructure\Persistence\DBAL\DbalPermissionRepository;
use App\IdentityAccess\Infrastructure\Persistence\DBAL\DbalRoleRepository;
use App\IdentityAccess\Infrastructure\Persistence\DBAL\DbalUserAccountRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\TestCase;

final class DbalIdentityRepositoryTest extends TestCase
{
    private Connection $db;

    protected function setUp(): void
    {
        $this->db = DriverManager::getConnection(['url' => 'sqlite:///:memory:']);
        $this->db->executeStatement('CREATE TABLE user_accounts (id CHAR(36) PRIMARY KEY, username VARCHAR(255) UNIQUE NOT NULL, password_hash VARCHAR(255) NOT NULL, person_id CHAR(36), status VARCHAR(30) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL)');
        $this->db->executeStatement('CREATE TABLE roles (id CHAR(36) PRIMARY KEY, name VARCHAR(100) UNIQUE NOT NULL, description VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL)');
        $this->db->executeStatement('CREATE TABLE permissions (id CHAR(36) PRIMARY KEY, code VARCHAR(100) UNIQUE NOT NULL, description VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL)');
        $this->db->executeStatement('CREATE TABLE user_roles (user_id CHAR(36) NOT NULL, role_id CHAR(36) NOT NULL, PRIMARY KEY(user_id, role_id))');
        $this->db->executeStatement('CREATE TABLE role_permissions (role_id CHAR(36) NOT NULL, permission_id CHAR(36) NOT NULL, PRIMARY KEY(role_id, permission_id))');
    }

    public function testUserRoleAndPermissionRoundTripPreservesTimestamps(): void
    {
        $users = new DbalUserAccountRepository($this->db, new \App\IdentityAccess\Infrastructure\Persistence\DBAL\UserAccountMapper());
        $roles = new DbalRoleRepository($this->db, new \App\IdentityAccess\Infrastructure\Persistence\DBAL\RoleMapper());
        $permissions = new DbalPermissionRepository($this->db, new \App\IdentityAccess\Infrastructure\Persistence\DBAL\PermissionMapper());

        $created = new DateTimeImmutable('2026-01-01 10:00:00');
        $updated = new DateTimeImmutable('2026-01-01 11:00:00');
        $role = Role::create(RoleId::generate(), 'Administrator', 'Admin role', $created);
        $permission = Permission::create(PermissionId::generate(), 'member.view', 'View members', $created);
        $this->db->insert('permissions', [
            'id' => $permission->id()->toString(),
            'code' => $permission->code(),
            'description' => $permission->description(),
            'created_at' => $permission->createdAt()->format('Y-m-d H:i:s'),
            'updated_at' => $permission->updatedAt()->format('Y-m-d H:i:s'),
        ]);
        $role->addPermission($permission->id(), $updated);
        $roles->save($role);

        $user = UserAccount::create(UserId::generate(), Username::fromString('admin'), PasswordHash::fromString('hash'), 'person-1', $created);
        $user->assignRole($role->id(), $updated);
        $users->save($user);

        $loaded = $users->findByUsername(Username::fromString('ADMIN'));
        self::assertNotNull($loaded);
        self::assertTrue($loaded->hasRole($role->id()));
        self::assertEquals($created, $loaded->createdAt());
        self::assertEquals($updated, $loaded->updatedAt());

        $loadedRole = $roles->findById($role->id());
        self::assertNotNull($loadedRole);
        self::assertTrue($loadedRole->hasPermission($permission->id()));
        self::assertEquals($created, $loadedRole->createdAt());
        self::assertEquals($updated, $loadedRole->updatedAt());

        $loadedPermission = $permissions->findByCode('member.view');
        self::assertNotNull($loadedPermission);
        self::assertEquals($created, $loadedPermission->createdAt());
        self::assertEquals($created, $loadedPermission->updatedAt());
    }
}
