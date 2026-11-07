<?php

declare(strict_types=1);

namespace App\Events\Application\Command\CancelEventOccurrences;

use App\Events\Application\OccurrenceChanges;
use App\IdentityAccess\Application\Contract\PermissionChecker;
use App\Shared\Application\CommandHandler;

final readonly class CancelEventOccurrencesHandler implements CommandHandler
{
    public function __construct(private PermissionChecker $authorization, private OccurrenceChanges $changes)
    {
    }

    public function handle(CancelEventOccurrencesCommand $command): void
    {
        $this->authorization->requireActorPermission($command->actorId, 'events.manage');
        $this->changes->apply($command->actorId, 'CANCEL', $command->draft, $command->reviewHash, $command->reason);
    }
}
