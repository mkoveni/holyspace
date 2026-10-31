<?php

declare(strict_types=1);

namespace App\IdentityAccess\Application\Command\DisableUserAccount;

use App\Shared\Application\CQRS\Command;

final readonly class DisableUserAccountCommand implements Command
{
    public function __construct(public string $userId)
    {
    }
}
