<?php

declare(strict_types=1);

namespace App\Events\Application\Command\ConvertVisitor;

use App\Shared\Application\Command;

/** @implements Command<null> */
final readonly class ConvertVisitorCommand implements Command
{
    public function __construct(
        public string $actorId,
        public string $registrationId,
        /** Also email the visitor an invitation to the church app. */
        public bool $inviteToApp,
    ) {
    }
}
