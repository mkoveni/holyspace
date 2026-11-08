<?php

declare(strict_types=1);

namespace App\Volunteering\Application\Command\InviteVolunteer;

use App\IdentityAccess\Application\Contract\PermissionChecker;
use App\Shared\Application\CommandHandler;
use App\Volunteering\Application\RosterPlanning;
use App\Volunteering\Application\ServingPermissions;

final readonly class InviteVolunteerHandler implements CommandHandler
{
    public function __construct(
        private PermissionChecker $auth,
        private RosterPlanning $planning,
    ) {
    }

    public function handle(InviteVolunteerCommand $command): string
    {
        $this->auth->requireActorPermission($command->actorId, ServingPermissions::MANAGE);

        return $this->planning->invite($command->actorId, $command->assignmentId, $command->expectedVersion, $command->expiresAt);
    }
}
