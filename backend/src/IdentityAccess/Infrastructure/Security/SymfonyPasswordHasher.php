<?php

declare(strict_types=1);

namespace App\IdentityAccess\Infrastructure\Security;

use App\IdentityAccess\Domain\Service\PasswordHasher;
use App\IdentityAccess\Domain\ValueObject\PasswordHash;

final class SymfonyPasswordHasher implements PasswordHasher
{
    public function hash(string $plainPassword): PasswordHash
    {
        return PasswordHash::fromString(password_hash($plainPassword, PASSWORD_ARGON2ID));
    }
    public function verify(string $plainPassword, PasswordHash $hash): bool
    {
        return password_verify($plainPassword, $hash->value());
    }
}
