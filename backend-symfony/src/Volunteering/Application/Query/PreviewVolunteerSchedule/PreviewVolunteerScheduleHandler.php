<?php

declare(strict_types=1);

namespace App\Volunteering\Application\Query\PreviewVolunteerSchedule;

use App\IdentityAccess\Application\Contract\PermissionChecker;
use App\Volunteering\Application\Contract\ShiftDraft;
use App\Volunteering\Application\ScheduleDates;
use App\Volunteering\Application\ServingPermissions;

/**
 * Java `PreviewVolunteerScheduleHandler`: read-only; the drafts are applied through the bulk command.
 */
final readonly class PreviewVolunteerScheduleHandler
{
    public function __construct(
        private PermissionChecker $auth,
    ) {
    }

    /** @return list<ShiftDraft> */
    public function handle(PreviewVolunteerScheduleQuery $query): array
    {
        $this->auth->requirePermission(ServingPermissions::READ);

        return ScheduleDates::preview($query->requestId, $query->teamId, $query->roleId, $query->dates, $query->startsAt, $query->endsAt, $query->timeZone, $query->positions);
    }
}
