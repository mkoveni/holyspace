<?php

declare(strict_types=1);

namespace App\Volunteering\Application\Query\ListVolunteerShifts;

use App\IdentityAccess\Application\Contract\PermissionChecker;
use App\Volunteering\Application\Contract\ShiftPage;
use App\Volunteering\Application\RosterViews;
use App\Volunteering\Application\ServingPermissions;

final readonly class ListVolunteerShiftsHandler
{
    public function __construct(
        private PermissionChecker $auth,
        private RosterViews $views,
    ) {
    }

    public function handle(ListVolunteerShiftsQuery $query): ShiftPage
    {
        $this->auth->requirePermission(ServingPermissions::READ);

        return $this->views->shifts($query->from, $query->until, $query->teamId, $query->limit, $query->offset);
    }
}
