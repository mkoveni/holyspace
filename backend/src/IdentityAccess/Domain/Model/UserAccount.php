<?php

declare(strict_types=1);

namespace App\IdentityAccess\Domain\Model;

use App\IdentityAccess\Domain\Enum\UserStatus;
use App\IdentityAccess\Domain\ValueObject\PasswordHash;
use App\IdentityAccess\Domain\ValueObject\RoleId;
use App\IdentityAccess\Domain\ValueObject\UserId;
use App\IdentityAccess\Domain\ValueObject\Username;
use App\Shared\Domain\ValueObject\AuditTimestamps;
use DateTimeImmutable;

final class UserAccount
{
    /** @var array<string, RoleId> */
    private array $roleIds = [];

    private function __construct(
        private readonly UserId $id,
        private readonly Username $username,
        private PasswordHash $passwordHash,
        private readonly ?string $personId,
        private UserStatus $status,
        private readonly AuditTimestamps $auditTimestamps,
    ) {}

    public static function create(
        UserId $id,
        Username $username,
        PasswordHash $passwordHash,
        ?string $personId,
    ): self {
        return new self(
            $id,
            $username,
            $passwordHash,
            $personId,
            UserStatus::ACTIVE,
            AuditTimestamps::now(),
        );
    }

    /** @param list<RoleId> $roleIds */
    public static function reconstitute(
        UserId $id,
        Username $username,
        PasswordHash $passwordHash,
        ?string $personId,
        UserStatus $status,
        AuditTimestamps $auditTimestamps,
        array $roleIds = [],
    ): self {
        $user = new self($id, $username, $passwordHash, $personId, $status, $auditTimestamps);
        foreach ($roleIds as $roleId) {
            $user->roleIds[$roleId->toString()] = $roleId;
        }
        return $user;
    }

    public function id(): UserId { return $this->id; }
    public function username(): Username { return $this->username; }
    public function passwordHash(): PasswordHash { return $this->passwordHash; }
    public function personId(): ?string { return $this->personId; }
    public function status(): UserStatus { return $this->status; }
    public function createdAt(): DateTimeImmutable { return $this->auditTimestamps->createdAt(); }
    public function updatedAt(): DateTimeImmutable { return $this->auditTimestamps->updatedAt(); }

    public function changePassword(PasswordHash $passwordHash): void
    {
        $this->passwordHash = $passwordHash;
        $this->auditTimestamps->touch();
    }

    public function disable(): void
    {
        if ($this->status !== UserStatus::DISABLED) {
            $this->status = UserStatus::DISABLED;
            $this->auditTimestamps->touch();
        }
    }

    public function enable(): void
    {
        if ($this->status !== UserStatus::ACTIVE) {
            $this->status = UserStatus::ACTIVE;
            $this->auditTimestamps->touch();
        }
    }

    public function lock(): void
    {
        if ($this->status !== UserStatus::LOCKED) {
            $this->status = UserStatus::LOCKED;
            $this->auditTimestamps->touch();
        }
    }

    public function unlock(): void
    {
        if ($this->status !== UserStatus::ACTIVE) {
            $this->status = UserStatus::ACTIVE;
            $this->auditTimestamps->touch();
        }
    }

    public function assignRole(RoleId $roleId): void
    {
        if (!$this->hasRole($roleId)) {
            $this->roleIds[$roleId->toString()] = $roleId;
            $this->auditTimestamps->touch();
        }
    }

    public function removeRole(RoleId $roleId): void
    {
        if ($this->hasRole($roleId)) {
            unset($this->roleIds[$roleId->toString()]);
            $this->auditTimestamps->touch();
        }
    }

    public function hasRole(RoleId $roleId): bool { return isset($this->roleIds[$roleId->toString()]); }

    /** @return list<RoleId> */
    public function roleIds(): array { return array_values($this->roleIds); }
}
