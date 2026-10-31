<?php

declare(strict_types=1);

namespace App\IdentityAccess\Application\Command\VerifyAuthenticator;

use App\Shared\Application\CQRS\Command;

final readonly class VerifyAuthenticatorCommand implements Command
{
    public function __construct(public string $code)
    {
    }
}
