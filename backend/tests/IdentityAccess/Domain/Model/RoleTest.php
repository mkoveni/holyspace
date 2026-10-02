<?php

declare(strict_types=1);

namespace App\Tests\IdentityAccess\Domain\Model;

use App\IdentityAccess\Domain\Model\Role;
use App\IdentityAccess\Domain\ValueObject\PermissionId;
use App\IdentityAccess\Domain\ValueObject\RoleId;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class RoleTest extends TestCase
{
    public function testPermissionAssignmentUpdatesAuditTimestamp(): void
    {
        $created = new DateTimeImmutable('2026-01-01 10:00:00');
        $updated = new DateTimeImmutable('2026-01-01 11:00:00');
        $role = Role::create(RoleId::generate(), 'Administrator', '', $created);
        $permission = PermissionId::generate();

        $role->addPermission($permission, $updated);
        $role->addPermission($permission, $updated);

        self::assertCount(1, $role->permissionIds());
        self::assertTrue($role->hasPermission($permission));
        self::assertEquals($created, $role->createdAt());
        self::assertEquals($updated, $role->updatedAt());

        $role->removePermission($permission, new DateTimeImmutable('2026-01-01 12:00:00'));
        self::assertFalse($role->hasPermission($permission));
    }
}
