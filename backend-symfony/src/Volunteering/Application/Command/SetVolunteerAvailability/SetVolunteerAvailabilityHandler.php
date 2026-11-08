<?php

declare(strict_types=1);

namespace App\Volunteering\Application\Command\SetVolunteerAvailability;

use App\IdentityAccess\Application\Contract\PermissionChecker;
use App\Shared\Application\CommandHandler;
use App\Volunteering\Application\ServingPermissions;
use App\Volunteering\Application\VolunteerAvailability;

final readonly class SetVolunteerAvailabilityHandler implements CommandHandler
{
    public function __construct(
        private PermissionChecker $auth,
        private VolunteerAvailability $availability,
    ) {
    }

    public function handle(SetVolunteerAvailabilityCommand $command): void
    {
        $this->auth->requireActorPermission($command->actorId, ServingPermissions::MANAGE);
        $this->availability->replace($command->actorId, $command->volunteerId, $command->expectedVersion, $command->weekly, $command->exceptions);
    }
}
