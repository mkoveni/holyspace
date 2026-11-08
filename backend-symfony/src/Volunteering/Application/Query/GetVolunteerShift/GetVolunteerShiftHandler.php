<?php

declare(strict_types=1);

namespace App\Volunteering\Application\Query\GetVolunteerShift;

use App\IdentityAccess\Application\Contract\PermissionChecker;
use App\Volunteering\Application\Contract\ShiftDetails;
use App\Volunteering\Application\RosterViews;
use App\Volunteering\Application\ServingPermissions;

final readonly class GetVolunteerShiftHandler
{
    public function __construct(
        private PermissionChecker $auth,
        private RosterViews $views,
    ) {
    }

    public function handle(GetVolunteerShiftQuery $query): ShiftDetails
    {
        $this->auth->requirePermission(ServingPermissions::READ);

        return $this->views->shift($query->shiftId);
    }
}
