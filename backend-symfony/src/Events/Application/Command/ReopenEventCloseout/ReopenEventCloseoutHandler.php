<?php

declare(strict_types=1);

namespace App\Events\Application\Command\ReopenEventCloseout;

use App\Events\Application\EventCloseouts;
use App\IdentityAccess\Application\Contract\PermissionChecker;
use App\Shared\Application\CommandHandler;

final readonly class ReopenEventCloseoutHandler implements CommandHandler
{
    public function __construct(private PermissionChecker $authorization, private EventCloseouts $closeouts)
    {
    }

    public function handle(ReopenEventCloseoutCommand $command): void
    {
        $this->authorization->requireActorPermission($command->actorId, 'events.manage');
        $this->closeouts->reopen($command->actorId, $command->occurrenceId, $command->expectedVersion, $command->reason);
    }
}
