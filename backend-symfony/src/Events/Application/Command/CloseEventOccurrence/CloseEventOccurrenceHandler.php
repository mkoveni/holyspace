<?php

declare(strict_types=1);

namespace App\Events\Application\Command\CloseEventOccurrence;

use App\Events\Application\EventCloseouts;
use App\IdentityAccess\Application\Contract\PermissionChecker;
use App\Shared\Application\CommandHandler;

final readonly class CloseEventOccurrenceHandler implements CommandHandler
{
    public function __construct(private PermissionChecker $authorization, private EventCloseouts $closeouts)
    {
    }

    public function handle(CloseEventOccurrenceCommand $command): void
    {
        $this->authorization->requireActorPermission($command->actorId, 'events.manage');
        $this->closeouts->close($command->actorId, $command->occurrenceId, $command->expectedVersion, $command->reviewHash, $command->note, $command->followUp, $command->attendanceReviewed, $command->followUpReviewed);
    }
}
