<?php

declare(strict_types=1);

namespace App\IdentityAccess\Application\Port;

interface SecretCipher
{
    public function encrypt(string $value): string;

    public function decrypt(string $encrypted): string;
}
