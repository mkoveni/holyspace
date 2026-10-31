<?php

declare(strict_types=1);

namespace App\IdentityAccess\Application\DTO;

final readonly class AuthenticatedUserView
{
    /** @param list<string> $roles */
    public function __construct(
        public string $id,
        public string $username,
        public ?string $personId,
        public string $status,
        public array $roles,
        public string $createdAt,
        public string $updatedAt,
        public array $permissions = [],
        public bool $mfaEnabled = false,
        public bool $mfaVerified = false,
    ) {
    }
}
