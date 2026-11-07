<?php

declare(strict_types=1);

namespace App\IdentityAccess\Presentation\Request;

final readonly class RoleRequest
{
    public function __construct(
        public string $name = '',
        public ?string $description = null,
    ) {
    }
}
