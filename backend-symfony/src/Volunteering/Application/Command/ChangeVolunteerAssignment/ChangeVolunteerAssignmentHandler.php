<?php

declare(strict_types=1);

namespace App\Volunteering\Application\Command\ChangeVolunteerAssignment;

use App\IdentityAccess\Application\Contract\PermissionChecker;
use App\Shared\Application\CommandHandler;
use App\Volunteering\Application\RosterPlanning;
use App\Volunteering\Application\ServingPermissions;

final readonly class ChangeVolunteerAssignmentHandler implements CommandHandler
{
    public function __construct(
        private PermissionChecker $auth,
        private RosterPlanning $planning,
    ) {
    }

    public function handle(ChangeVolunteerAssignmentCommand $command): void
    {
        $this->auth->requireActorPermission($command->actorId, ServingPermissions::MANAGE);
        $this->planning->change($command->actorId, $command->assignmentId, $command->expectedVersion, $command->status, $command->reason);
    }
}
