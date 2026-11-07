<?php

declare(strict_types=1);

namespace App\IdentityAccess\Application\Contract;

interface PasswordHasher
{
    public function hash(string $password): string;

    public function matches(?string $password, string $hash): bool;

    /** A valid hash of an unknown password, verified when no account matches to keep timing uniform. */
    public function dummyHash(): string;
}
