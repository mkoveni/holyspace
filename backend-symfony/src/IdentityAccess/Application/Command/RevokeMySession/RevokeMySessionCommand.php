<?php

declare(strict_types=1);

namespace App\IdentityAccess\Application\Command\RevokeMySession;

use App\Shared\Application\Command;

/** @implements Command<null> */
final readonly class RevokeMySessionCommand implements Command
{
    public function __construct(
        public string $actorId,
        public string $sessionId,
    ) {
    }
}
