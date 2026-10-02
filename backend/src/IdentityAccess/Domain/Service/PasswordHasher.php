<?php

declare(strict_types=1);

namespace App\IdentityAccess\Domain\Service;

use App\IdentityAccess\Domain\ValueObject\PasswordHash;

interface PasswordHasher
{
    public function hash(string $plainPassword): PasswordHash;
    public function verify(string $plainPassword, PasswordHash $hash): bool;
}
