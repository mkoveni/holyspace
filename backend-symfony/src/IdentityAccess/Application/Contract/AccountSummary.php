<?php

declare(strict_types=1);

namespace App\IdentityAccess\Application\Contract;

use App\IdentityAccess\Domain\ValueObject\AccountStatus;
use DateTimeImmutable;

/** Read model of an account: what queries and other contexts may know, never the password hash or the aggregate. */
final readonly class AccountSummary
{
    public function __construct(
        public string $id,
        public string $username,
        public string $email,
        public string $displayName,
        public string $status,
        public int $version,
        public DateTimeImmutable $createdAt,
        public DateTimeImmutable $updatedAt,
    ) {
    }

    public function isActive(): bool
    {
        return AccountStatus::Active->value === $this->status;
    }
}
