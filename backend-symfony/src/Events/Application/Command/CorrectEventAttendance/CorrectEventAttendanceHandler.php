<?php

declare(strict_types=1);

namespace App\Events\Application\Command\CorrectEventAttendance;

use App\Events\Application\EventAttendance;
use App\IdentityAccess\Application\Contract\PermissionChecker;
use App\Shared\Application\CommandHandler;

final readonly class CorrectEventAttendanceHandler implements CommandHandler
{
    public function __construct(private PermissionChecker $authorization, private EventAttendance $attendance)
    {
    }

    public function handle(CorrectEventAttendanceCommand $command): void
    {
        $this->authorization->requireActorPermission($command->actorId, 'events.attendance.manage');
        $this->attendance->correct($command->actorId, $command->registrationId, $command->expectedVersion, $command->status, $command->reason);
    }
}
