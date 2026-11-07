<?php

declare(strict_types=1);

namespace App\Events\Application\Command\ScanMemberAttendance;

use App\Events\Application\MemberEventActions;
use App\IdentityAccess\Application\Contract\PermissionChecker;
use App\Shared\Application\CommandHandler;

final readonly class ScanMemberAttendanceHandler implements CommandHandler
{
    public function __construct(private PermissionChecker $authorization, private MemberEventActions $actions)
    {
    }

    /** @return string the attendance id */
    public function handle(ScanMemberAttendanceCommand $command): string
    {
        $this->authorization->requireActorPermission($command->actorId, 'events.attendance.manage');

        return $this->actions->scan($command->actorId, $command->occurrenceId, $command->requestId, $command->credential);
    }
}
