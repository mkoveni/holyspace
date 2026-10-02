<?php

declare(strict_types=1);

namespace App\IdentityAccess\Infrastructure\Persistence\DBAL;

use App\IdentityAccess\Domain\Model\Permission;
use App\IdentityAccess\Domain\ValueObject\PermissionId;
use App\Shared\Domain\ValueObject\AuditTimestamps;
use DateTimeImmutable;

final class PermissionMapper
{
    public function toDomain(array $row): Permission
    {
        return Permission::reconstitute(
            PermissionId::fromString((string) $row['id']),
            (string) $row['code'],
            (string) $row['description'],
            new AuditTimestamps($this->date($row['created_at']), $this->date($row['updated_at'])),
        );
    }

    /** @return array<string, mixed> */
    public function toRow(Permission $permission): array
    {
        return [
            'id' => $permission->id()->toString(),
            'code' => $permission->code(),
            'description' => $permission->description(),
            'created_at' => $permission->createdAt()->format('Y-m-d H:i:s'),
            'updated_at' => $permission->updatedAt()->format('Y-m-d H:i:s'),
        ];
    }

    private function date(mixed $value): DateTimeImmutable
    {
        return $value instanceof DateTimeImmutable ? $value : new DateTimeImmutable((string) $value);
    }
}
