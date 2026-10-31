<?php

declare(strict_types=1);

namespace App\IdentityAccess\Application\Command\EnableUserAccount;

use App\Shared\Application\CQRS\Command;

final readonly class EnableUserAccountCommand implements Command
{
    public function __construct(public string $userId)
    {
    }
}
