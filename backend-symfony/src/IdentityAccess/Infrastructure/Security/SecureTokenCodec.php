<?php

declare(strict_types=1);

namespace App\IdentityAccess\Infrastructure\Security;

use App\IdentityAccess\Application\Contract\TokenCodec;
use SensitiveParameter;

final class SecureTokenCodec implements TokenCodec
{
    public function generate(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    }

    public function hash(#[SensitiveParameter] string $token): string
    {
        return hash('sha256', $token);
    }
}
