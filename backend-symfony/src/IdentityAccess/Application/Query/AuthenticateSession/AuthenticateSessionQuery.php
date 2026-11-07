<?php

declare(strict_types=1);

namespace App\IdentityAccess\Application\Query\AuthenticateSession;

use SensitiveParameter;

final readonly class AuthenticateSessionQuery
{
    public function __construct(#[SensitiveParameter] public ?string $token)
    {
    }

    /** @return array<string, string> */
    public function __debugInfo(): array
    {
        return ['token' => '[redacted]'];
    }
}
