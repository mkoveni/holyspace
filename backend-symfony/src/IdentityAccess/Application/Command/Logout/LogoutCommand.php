<?php

declare(strict_types=1);

namespace App\IdentityAccess\Application\Command\Logout;

use App\Shared\Application\Command;

/** @implements Command<null> */
final readonly class LogoutCommand implements Command
{
    public function __construct(public string $accountId, public string $sessionId)
    {
    }
}
