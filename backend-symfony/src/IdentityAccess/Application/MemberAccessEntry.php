<?php

declare(strict_types=1);

namespace App\IdentityAccess\Application;

final readonly class MemberAccessEntry
{
    public function __construct(
        public string $accountId,
        public string $accountName,
        public string $personId,
        public string $personName,
        public bool $active,
        public int $version,
    ) {
    }
}
