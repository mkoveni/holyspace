<?php

declare(strict_types=1);

namespace App\Volunteering\Application\Command\CreateVolunteerShift;

use App\IdentityAccess\Application\Contract\PermissionChecker;
use App\Shared\Application\CommandHandler;
use App\Volunteering\Application\RosterPlanning;
use App\Volunteering\Application\ServingPermissions;

final readonly class CreateVolunteerShiftHandler implements CommandHandler
{
    public function __construct(
        private PermissionChecker $auth,
        private RosterPlanning $planning,
    ) {
    }

    public function handle(CreateVolunteerShiftCommand $command): string
    {
        $this->auth->requireActorPermission($command->actorId, ServingPermissions::MANAGE);

        return $this->planning->create($command->actorId, $command->draft);
    }
}
