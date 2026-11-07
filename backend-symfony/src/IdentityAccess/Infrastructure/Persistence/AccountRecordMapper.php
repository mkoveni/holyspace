<?php

declare(strict_types=1);

namespace App\IdentityAccess\Infrastructure\Persistence;

use App\IdentityAccess\Application\Contract\AccountSummary;
use App\IdentityAccess\Domain\Account;
use App\Shared\Domain\Revision;
use App\Shared\Infrastructure\Persistence\Timestamps;

/** The single place where user_accounts rows become aggregates and back. */
final class AccountRecordMapper
{
    private function __construct()
    {
    }

    /** @param array<string, mixed> $row */
    public static function toDomain(array $row): Account
    {
        return Account::reconstitute(
            (string) $row['id'],
            (string) $row['username'],
            (string) $row['email'],
            (string) $row['display_name'],
            (string) $row['password_hash'],
            (string) $row['status'],
            Revision::reconstitute((int) $row['version'], Timestamps::fromDatabase((string) $row['created_at']), Timestamps::fromDatabase((string) $row['updated_at'])),
        );
    }

    /** @param array<string, mixed> $row */
    public static function toSummary(array $row): AccountSummary
    {
        return new AccountSummary(
            (string) $row['id'],
            (string) $row['username'],
            (string) $row['email'],
            (string) $row['display_name'],
            (string) $row['status'],
            (int) $row['version'],
            Timestamps::fromDatabase((string) $row['created_at']),
            Timestamps::fromDatabase((string) $row['updated_at']),
        );
    }

    /** @return array<string, string|int> */
    public static function toRow(Account $account): array
    {
        return [
            'id' => $account->id,
            'username' => $account->username->value,
            'email' => $account->email->value,
            'display_name' => $account->displayName->value,
            'password_hash' => $account->passwordHash->value,
            'status' => $account->status->value,
            'version' => $account->revision->version,
            'created_at' => Timestamps::toDatabase($account->revision->timeline->createdAt),
            'updated_at' => Timestamps::toDatabase($account->revision->timeline->updatedAt),
        ];
    }
}
