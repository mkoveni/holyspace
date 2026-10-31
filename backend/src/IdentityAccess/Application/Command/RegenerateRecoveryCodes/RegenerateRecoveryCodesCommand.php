<?php

declare(strict_types=1);

namespace App\IdentityAccess\Application\Command\RegenerateRecoveryCodes;

use App\Shared\Application\CQRS\Command;

final readonly class RegenerateRecoveryCodesCommand implements Command
{
    public function __construct(public string $password)
    {
    }
}
