<?php

declare(strict_types=1);

namespace App\Volunteering\Application\Query\GetOwnVolunteerSchedule;

use App\IdentityAccess\Application\Contract\PermissionChecker;
use App\Volunteering\Application\Contract\InvitationDetails;
use App\Volunteering\Application\RosterViews;
use App\Volunteering\Application\ServingPermissions;

final readonly class GetOwnVolunteerScheduleHandler
{
    public function __construct(
        private PermissionChecker $auth,
        private RosterViews $views,
    ) {
    }

    /** @return list<InvitationDetails> */
    public function handle(GetOwnVolunteerScheduleQuery $query): array
    {
        $this->auth->requirePermission(ServingPermissions::SERVE);
        $this->auth->requireActorPermission($query->actorId, ServingPermissions::SERVE);

        return $this->views->own($query->actorId, $query->limit, $query->offset);
    }
}
