<?php

declare(strict_types=1);

namespace App\Volunteering\Application\Command\ExpireVolunteerInvitations;

use App\IdentityAccess\Application\Contract\PermissionChecker;
use App\Shared\Application\CommandHandler;
use App\Volunteering\Application\RosterPlanning;
use App\Volunteering\Application\ServingPermissions;

/**
 * Java `ExpireVolunteerInvitationsHandler`. Java expired each invitation in its own transaction; here the bus runs
 * the batch (at most 100) in one.
 */
final readonly class ExpireVolunteerInvitationsHandler implements CommandHandler
{
    public function __construct(
        private PermissionChecker $auth,
        private RosterPlanning $planning,
    ) {
    }

    public function handle(ExpireVolunteerInvitationsCommand $command): int
    {
        $this->auth->requireActorPermission($command->actorId, ServingPermissions::MANAGE);

        return $this->planning->expire($command->actorId, $command->limit);
    }
}
