<?php

declare(strict_types=1);

namespace App\IdentityAccess\Application\Command\SetupAuthenticator;

use App\Shared\Application\CQRS\Command;

final readonly class SetupAuthenticatorCommand implements Command
{
    public function __construct(public string $password)
    {
    }
}
