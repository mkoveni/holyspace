<?php

declare(strict_types=1);

namespace App\Volunteering\Application\Command\RespondVolunteerInvitation;

use App\IdentityAccess\Application\Contract\PermissionChecker;
use App\Shared\Application\CommandHandler;
use App\Volunteering\Application\RosterPlanning;
use App\Volunteering\Application\ServingPermissions;

/**
 * Java `RespondVolunteerInvitationHandler`: only the account linked to the invited volunteer may answer.
 */
final readonly class RespondVolunteerInvitationHandler implements CommandHandler
{
    public function __construct(
        private PermissionChecker $auth,
        private RosterPlanning $planning,
    ) {
    }

    public function handle(RespondVolunteerInvitationCommand $command): void
    {
        $this->auth->requireActorPermission($command->actorId, ServingPermissions::SERVE);
        $this->planning->respond($command->actorId, $command->actorId, $command->invitationId, $command->expectedVersion, $command->decision);
    }
}
