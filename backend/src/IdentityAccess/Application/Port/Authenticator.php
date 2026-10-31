<?php

declare(strict_types=1);

namespace App\IdentityAccess\Application\Port;

interface Authenticator
{
    /** @return array{secret: string, uri: string} */
    public function create(string $username): array;

    public function step(string $secret, string $code, int $last): int;
}
