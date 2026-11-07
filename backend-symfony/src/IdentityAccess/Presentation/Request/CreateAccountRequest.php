<?php

declare(strict_types=1);

namespace App\IdentityAccess\Presentation\Request;

final readonly class CreateAccountRequest
{
    public function __construct(
        public string $username = '',
        public string $email = '',
        public string $displayName = '',
        public string $password = '',
    ) {
    }
}
