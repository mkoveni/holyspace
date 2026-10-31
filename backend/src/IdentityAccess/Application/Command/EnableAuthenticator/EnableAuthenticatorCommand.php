<?php

declare(strict_types=1);

namespace App\IdentityAccess\Application\Command\EnableAuthenticator;

use App\Shared\Application\CQRS\Command;

final readonly class EnableAuthenticatorCommand implements Command
{
    public function __construct(public string $password, public string $code)
    {
    }
}
