<?php

declare(strict_types=1);

namespace App\IdentityAccess\Presentation\Request;

final readonly class PermissionRequest
{
    public function __construct(
        public string $code = '',
        public ?string $description = null,
    ) {
    }
}
