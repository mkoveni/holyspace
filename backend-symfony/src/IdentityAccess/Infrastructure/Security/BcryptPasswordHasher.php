<?php

declare(strict_types=1);

namespace App\IdentityAccess\Infrastructure\Security;

use App\IdentityAccess\Application\Contract\PasswordHasher;
use SensitiveParameter;

final readonly class BcryptPasswordHasher implements PasswordHasher
{
    /** bcrypt (cost 12) of a random value nobody knows; avoids a 250 ms hash per unknown-user login. */
    private const string DUMMY_HASH = '$2y$12$1n5HDGoLiQ8DpXsRQDkeF.Xx1cCtvzaWyQ3eHcpjCmCVAb6WzHtjK';

    public function __construct(private int $cost = 12)
    {
    }

    public function hash(#[SensitiveParameter] string $password): string
    {
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => $this->cost]);
    }

    public function matches(#[SensitiveParameter] ?string $password, string $hash): bool
    {
        return null !== $password && strlen($password) <= 72 && password_verify($password, $hash);
    }

    public function dummyHash(): string
    {
        return self::DUMMY_HASH;
    }
}
