<?php

declare(strict_types=1);

namespace App\IdentityAccess\Domain\Model;

use App\IdentityAccess\Domain\ValueObject\PermissionId;
use App\IdentityAccess\Domain\ValueObject\RoleId;
use App\Shared\Domain\ValueObject\AuditTimestamps;
use DateTimeImmutable;
use InvalidArgumentException;

final class Role
{
    /** @var array<string, PermissionId> */
    private array $permissionIds = [];

    private function __construct(
        private readonly RoleId $id,
        private string $name,
        private string $description,
        private readonly AuditTimestamps $auditTimestamps,
    ) {
    }

    public static function create(RoleId $id, string $name, string $description, ?DateTimeImmutable $at = null): self
    {
        if ('' === trim($name)) {
            throw new InvalidArgumentException('Role name cannot be empty.');
        }

        return new self($id, trim($name), trim($description), AuditTimestamps::now($at));
    }

    /** @param list<PermissionId> $permissionIds */
    public static function reconstitute(
        RoleId $id,
        string $name,
        string $description,
        AuditTimestamps $auditTimestamps,
        array $permissionIds = [],
    ): self {
        $role = new self($id, trim($name), trim($description), $auditTimestamps);
        foreach ($permissionIds as $permissionId) {
            $role->permissionIds[$permissionId->toString()] = $permissionId;
        }

        return $role;
    }

    public function id(): RoleId
    {
        return $this->id;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function description(): string
    {
        return $this->description;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->auditTimestamps->createdAt();
    }

    public function updatedAt(): DateTimeImmutable
    {
        return $this->auditTimestamps->updatedAt();
    }

    public function rename(string $name, ?DateTimeImmutable $at = null): void
    {
        $name = trim($name);
        if ('' === $name) {
            throw new InvalidArgumentException('Role name cannot be empty.');
        }
        if ($this->name !== $name) {
            $this->name = $name;
            $this->auditTimestamps->touch($at);
        }
    }

    public function changeDescription(string $description, ?DateTimeImmutable $at = null): void
    {
        $description = trim($description);
        if ($this->description !== $description) {
            $this->description = $description;
            $this->auditTimestamps->touch($at);
        }
    }

    public function addPermission(PermissionId $permissionId, ?DateTimeImmutable $at = null): void
    {
        if (!$this->hasPermission($permissionId)) {
            $this->permissionIds[$permissionId->toString()] = $permissionId;
            $this->auditTimestamps->touch($at);
        }
    }

    public function removePermission(PermissionId $permissionId, ?DateTimeImmutable $at = null): void
    {
        if ($this->hasPermission($permissionId)) {
            unset($this->permissionIds[$permissionId->toString()]);
            $this->auditTimestamps->touch($at);
        }
    }

    /** @return list<PermissionId> */
    public function permissionIds(): array
    {
        return array_values($this->permissionIds);
    }

    public function hasPermission(PermissionId $permissionId): bool
    {
        return isset($this->permissionIds[$permissionId->toString()]);
    }
}
