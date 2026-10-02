<?php

declare(strict_types=1);

namespace App\IdentityAccess\Infrastructure\Persistence\DBAL;

use App\IdentityAccess\Domain\Enum\UserStatus;
use App\IdentityAccess\Domain\Model\UserAccount;
use App\IdentityAccess\Domain\ValueObject\PasswordHash;
use App\IdentityAccess\Domain\ValueObject\RoleId;
use App\IdentityAccess\Domain\ValueObject\UserId;
use App\IdentityAccess\Domain\ValueObject\Username;
use App\Shared\Domain\ValueObject\AuditTimestamps;
use DateTimeImmutable;

final class UserAccountMapper
{
    /** @param list<string> $roleIds */
    public function toDomain(array $row, array $roleIds): UserAccount
    {
        return UserAccount::reconstitute(
            UserId::fromString((string) $row['id']),
            Username::fromString((string) $row['username']),
            PasswordHash::fromString((string) $row['password_hash']),
            $row['person_id'] !== null && $row['person_id'] !== '' ? (string) $row['person_id'] : null,
            UserStatus::from((string) $row['status']),
            new AuditTimestamps($this->date($row['created_at']), $this->date($row['updated_at'])),
            array_map(static fn(string $id): RoleId => RoleId::fromString($id), $roleIds),
        );
    }

    /** @return array<string, mixed> */
    public function toRow(UserAccount $user): array
    {
        return [
            'id' => $user->id()->toString(),
            'username' => $user->username()->value(),
            'password_hash' => $user->passwordHash()->value(),
            'person_id' => $user->personId(),
            'status' => $user->status()->value,
            'created_at' => $user->createdAt()->format('Y-m-d H:i:s'),
            'updated_at' => $user->updatedAt()->format('Y-m-d H:i:s'),
        ];
    }

    private function date(mixed $value): DateTimeImmutable
    {
        return $value instanceof DateTimeImmutable ? $value : new DateTimeImmutable((string) $value);
    }
}
