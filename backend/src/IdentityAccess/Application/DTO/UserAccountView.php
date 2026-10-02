<?php

declare(strict_types=1);

namespace App\IdentityAccess\Application\DTO;

final readonly class UserAccountView
{
    /** @param list<string> $roleIds */
    public function __construct(
        public string $id,
        public string $username,
        public ?string $personId,
        public string $status,
        public array $roleIds,
        public string $createdAt,
        public string $updatedAt,
    ) {}
}
