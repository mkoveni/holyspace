<?php

declare(strict_types=1);

namespace App\Events\Application\Command\CheckInEventPerson;

use App\Events\Application\EventAttendance;
use App\IdentityAccess\Application\Contract\PermissionChecker;
use App\Shared\Application\CommandHandler;

final readonly class CheckInEventPersonHandler implements CommandHandler
{
    public function __construct(private PermissionChecker $authorization, private EventAttendance $attendance)
    {
    }

    /** @return string the attendance id */
    public function handle(CheckInEventPersonCommand $command): string
    {
        $this->authorization->requireActorPermission($command->actorId, 'events.attendance.manage');

        return $this->attendance->checkIn($command->actorId, $command->requestId, $command->registrationId, $command->expectedVersion);
    }
}
