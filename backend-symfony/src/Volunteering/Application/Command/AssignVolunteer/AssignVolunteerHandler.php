<?php

declare(strict_types=1);

namespace App\Volunteering\Application\Command\AssignVolunteer;

use App\IdentityAccess\Application\Contract\PermissionChecker;
use App\Shared\Application\CommandHandler;
use App\Volunteering\Application\RosterPlanning;
use App\Volunteering\Application\ServingPermissions;

final readonly class AssignVolunteerHandler implements CommandHandler
{
    public function __construct(
        private PermissionChecker $auth,
        private RosterPlanning $planning,
    ) {
    }

    public function handle(AssignVolunteerCommand $command): string
    {
        $this->auth->requireActorPermission($command->actorId, ServingPermissions::MANAGE);

        return $this->planning->assign($command->actorId, $command->requestId, $command->shiftId, $command->expectedVersion, $command->volunteerId, $command->status);
    }
}
