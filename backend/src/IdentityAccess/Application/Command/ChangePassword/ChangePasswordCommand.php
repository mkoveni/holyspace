<?php

declare(strict_types=1);

namespace App\IdentityAccess\Application\Command\ChangePassword;

use App\Shared\Application\CQRS\Command;

final readonly class ChangePasswordCommand implements Command
{
    public function __construct(public string $userId, public string $plainPassword)
    {
    }
}
