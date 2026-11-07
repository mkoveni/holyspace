<?php

declare(strict_types=1);

namespace App\Events\Application\Command\RegisterEventWalkIn;

use App\Events\Application\EventAttendance;
use App\Events\Application\WalkIn;
use App\IdentityAccess\Application\Contract\PermissionChecker;
use App\Shared\Application\CommandHandler;

final readonly class RegisterEventWalkInHandler implements CommandHandler
{
    public function __construct(private PermissionChecker $authorization, private EventAttendance $attendance)
    {
    }

    public function handle(RegisterEventWalkInCommand $command): WalkIn
    {
        $this->authorization->requireActorPermission($command->actorId, 'events.attendance.manage');

        return $this->attendance->walkIn($command->actorId, $command->requestId, $command->occurrenceId, $command->personId, $command->expectedVersion);
    }
}
