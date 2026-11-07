<?php

declare(strict_types=1);

namespace App\IdentityAccess\Application;

use App\IdentityAccess\Application\Contract\AccountSummary;
use DateTimeImmutable;

final readonly class AccountView
{
    /**
     * @param list<string> $roles
     * @param list<string> $permissions
     */
    public function __construct(
        public string $id,
        public string $username,
        public string $email,
        public string $displayName,
        public ?string $personId,
        public string $status,
        public array $roles,
        public array $permissions,
        public int $version,
        public DateTimeImmutable $createdAt,
        public DateTimeImmutable $updatedAt,
    ) {
    }

    /**
     * @param list<string> $roles
     * @param list<string> $permissions
     */
    public static function from(AccountSummary $account, array $roles, array $permissions): self
    {
        return new self(
            $account->id,
            $account->username,
            $account->email,
            $account->displayName,
            null,
            $account->status,
            $roles,
            $permissions,
            $account->version,
            $account->createdAt,
            $account->updatedAt,
        );
    }
}
