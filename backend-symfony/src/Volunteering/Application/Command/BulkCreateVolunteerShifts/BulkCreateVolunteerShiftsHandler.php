<?php

declare(strict_types=1);

namespace App\Volunteering\Application\Command\BulkCreateVolunteerShifts;

use App\IdentityAccess\Application\Contract\PermissionChecker;
use App\Shared\Application\CommandHandler;
use App\Volunteering\Application\RosterPlanning;
use App\Volunteering\Application\ServingPermissions;

final readonly class BulkCreateVolunteerShiftsHandler implements CommandHandler
{
    public function __construct(
        private PermissionChecker $auth,
        private RosterPlanning $planning,
    ) {
    }

    /** @return list<string> */
    public function handle(BulkCreateVolunteerShiftsCommand $command): array
    {
        $this->auth->requireActorPermission($command->actorId, ServingPermissions::MANAGE);

        return $this->planning->bulk($command->actorId, $command->drafts);
    }
}
