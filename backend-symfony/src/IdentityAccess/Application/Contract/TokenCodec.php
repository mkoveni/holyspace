<?php

declare(strict_types=1);

namespace App\IdentityAccess\Application\Contract;

interface TokenCodec
{
    /** @return string a URL-safe token with 256 bits of entropy (43 characters) */
    public function generate(): string;

    public function hash(string $token): string;
}
