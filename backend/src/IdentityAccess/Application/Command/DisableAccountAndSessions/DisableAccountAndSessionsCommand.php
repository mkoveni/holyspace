<?php

declare(strict_types=1);

namespace App\IdentityAccess\Application\Command\DisableAccountAndSessions;

use App\Shared\Application\CQRS\Command;

final readonly class DisableAccountAndSessionsCommand implements Command
{
    public function __construct(public string $id)
    {
    }
}
