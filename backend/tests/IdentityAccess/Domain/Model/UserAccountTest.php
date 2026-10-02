<?php

declare(strict_types=1);

namespace App\Tests\IdentityAccess\Domain\Model;

use App\IdentityAccess\Domain\Enum\UserStatus;
use App\IdentityAccess\Domain\Model\UserAccount;
use App\IdentityAccess\Domain\ValueObject\PasswordHash;
use App\IdentityAccess\Domain\ValueObject\RoleId;
use App\IdentityAccess\Domain\ValueObject\UserId;
use App\IdentityAccess\Domain\ValueObject\Username;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class UserAccountTest extends TestCase
{
    public function testCreatesActiveAccountWithAuditTimestamps(): void
    {
        $now = new DateTimeImmutable('2026-01-01 10:00:00');
        $user = UserAccount::create(UserId::generate(), Username::fromString(' Admin@Church.org '), PasswordHash::fromString('hash'), 'person-id', $now);
        self::assertSame('admin@church.org', $user->username()->value());
        self::assertSame(UserStatus::ACTIVE, $user->status());
        self::assertSame('person-id', $user->personId());
        self::assertEquals($now, $user->createdAt());
        self::assertEquals($now, $user->updatedAt());
    }

    public function testRoleAssignmentChangesUpdatedAtButNotCreatedAt(): void
    {
        $created = new DateTimeImmutable('2026-01-01 10:00:00');
        $updated = new DateTimeImmutable('2026-01-01 11:00:00');
        $user = UserAccount::create(UserId::generate(), Username::fromString('admin'), PasswordHash::fromString('hash'), null, $created);
        $role = RoleId::generate();

        $user->assignRole($role, $updated);
        $user->assignRole($role, $updated);

        self::assertCount(1, $user->roleIds());
        self::assertTrue($user->hasRole($role));
        self::assertEquals($created, $user->createdAt());
        self::assertEquals($updated, $user->updatedAt());

        $removed = new DateTimeImmutable('2026-01-01 12:00:00');
        $user->removeRole($role, $removed);
        self::assertFalse($user->hasRole($role));
        self::assertEquals($removed, $user->updatedAt());
    }

    public function testCanDisableEnableAndLockUnlock(): void
    {
        $created = new DateTimeImmutable('2026-01-01 10:00:00');
        $user = UserAccount::create(UserId::generate(), Username::fromString('admin'), PasswordHash::fromString('hash'), null, $created);
        $user->disable(new DateTimeImmutable('2026-01-01 10:01:00'));
        self::assertSame(UserStatus::DISABLED, $user->status());
        $user->enable(new DateTimeImmutable('2026-01-01 10:02:00'));
        self::assertSame(UserStatus::ACTIVE, $user->status());
        $user->lock(new DateTimeImmutable('2026-01-01 10:03:00'));
        self::assertSame(UserStatus::LOCKED, $user->status());
        $user->unlock(new DateTimeImmutable('2026-01-01 10:04:00'));
        self::assertSame(UserStatus::ACTIVE, $user->status());
        self::assertEquals(new DateTimeImmutable('2026-01-01 10:04:00'), $user->updatedAt());
    }

    public function testReconstitutionPreservesTimestamps(): void
    {
        $created = new DateTimeImmutable('2025-05-01 09:00:00');
        $updated = new DateTimeImmutable('2025-06-01 09:00:00');
        $user = UserAccount::reconstitute(
            UserId::generate(),
            Username::fromString('admin'),
            PasswordHash::fromString('hash'),
            null,
            UserStatus::ACTIVE,
            new \App\Shared\Domain\ValueObject\AuditTimestamps($created, $updated),
        );
        self::assertEquals($created, $user->createdAt());
        self::assertEquals($updated, $user->updatedAt());
    }
}
