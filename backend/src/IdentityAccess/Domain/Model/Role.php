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
    ) {}

    public static function create(RoleId $id, string $name, string $description, DateTimeImmutable $now): self
    {
        if (trim($name) === '') {
            throw new InvalidArgumentException('Role name cannot be empty.');
        }
        return new self($id, trim($name), trim($description), new AuditTimestamps($now, $now));
    }

    /** @param list<PermissionId> $permissionIds */
    public static function reconstitute(
        RoleId $id,
        string $name,
        string $description,
        AuditTimestamps $auditTimestamps,
        array $permissionIds = [],
    ): self {
        $role = self::create($id, $name, $description, $auditTimestamps->createdAt());
        $role->auditTimestamps->touch($auditTimestamps->updatedAt());
        foreach ($permissionIds as $permissionId) {
            $role->permissionIds[$permissionId->toString()] = $permissionId;
        }
        return $role;
    }

    public function id(): RoleId { return $this->id; }
    public function name(): string { return $this->name; }
    public function description(): string { return $this->description; }
    public function createdAt(): DateTimeImmutable { return $this->auditTimestamps->createdAt(); }
    public function updatedAt(): DateTimeImmutable { return $this->auditTimestamps->updatedAt(); }

    public function rename(string $name, DateTimeImmutable $now): void
    {
        $name = trim($name);
        if ($name === '') throw new InvalidArgumentException('Role name cannot be empty.');
        if ($this->name !== $name) {
            $this->name = $name;
            $this->auditTimestamps->touch($now);
        }
    }

    public function changeDescription(string $description, DateTimeImmutable $now): void
    {
        $description = trim($description);
        if ($this->description !== $description) {
            $this->description = $description;
            $this->auditTimestamps->touch($now);
        }
    }

    public function addPermission(PermissionId $permissionId, DateTimeImmutable $now): void
    {
        if (!$this->hasPermission($permissionId)) {
            $this->permissionIds[$permissionId->toString()] = $permissionId;
            $this->auditTimestamps->touch($now);
        }
    }

    public function removePermission(PermissionId $permissionId, DateTimeImmutable $now): void
    {
        if ($this->hasPermission($permissionId)) {
            unset($this->permissionIds[$permissionId->toString()]);
            $this->auditTimestamps->touch($now);
        }
    }

    /** @return list<PermissionId> */
    public function permissionIds(): array { return array_values($this->permissionIds); }
    public function hasPermission(PermissionId $permissionId): bool { return isset($this->permissionIds[$permissionId->toString()]); }
}
