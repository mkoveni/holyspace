<?php

declare(strict_types=1);

namespace App\Volunteering\Application\Command\UpdateVolunteerShift;

use App\IdentityAccess\Application\Contract\PermissionChecker;
use App\Shared\Application\CommandHandler;
use App\Volunteering\Application\RosterPlanning;
use App\Volunteering\Application\ServingPermissions;

final readonly class UpdateVolunteerShiftHandler implements CommandHandler
{
    public function __construct(
        private PermissionChecker $auth,
        private RosterPlanning $planning,
    ) {
    }

    public function handle(UpdateVolunteerShiftCommand $command): void
    {
        $this->auth->requireActorPermission($command->actorId, ServingPermissions::MANAGE);
        $this->planning->update($command->actorId, $command->shiftId, $command->expectedVersion, $command->startsAt, $command->endsAt, $command->positions, $command->status);
    }
}
