<?php

declare(strict_types=1);

namespace App\IdentityAccess\Infrastructure\Persistence\DBAL;

use App\IdentityAccess\Domain\Model\Role;
use App\IdentityAccess\Domain\ValueObject\PermissionId;
use App\IdentityAccess\Domain\ValueObject\RoleId;
use App\Shared\Domain\ValueObject\AuditTimestamps;
use DateTimeImmutable;

final class RoleMapper
{
    /** @param list<string> $permissionIds */
    public function toDomain(array $row, array $permissionIds): Role
    {
        return Role::reconstitute(
            RoleId::fromString((string) $row['id']),
            (string) $row['name'],
            (string) $row['description'],
            new AuditTimestamps($this->date($row['created_at']), $this->date($row['updated_at'])),
            array_map(static fn(string $id): PermissionId => PermissionId::fromString($id), $permissionIds),
        );
    }

    /** @return array<string, mixed> */
    public function toRow(Role $role): array
    {
        return [
            'id' => $role->id()->toString(),
            'name' => $role->name(),
            'description' => $role->description(),
            'created_at' => $role->createdAt()->format('Y-m-d H:i:s'),
            'updated_at' => $role->updatedAt()->format('Y-m-d H:i:s'),
        ];
    }

    private function date(mixed $value): DateTimeImmutable
    {
        return $value instanceof DateTimeImmutable ? $value : new DateTimeImmutable((string) $value);
    }
}
