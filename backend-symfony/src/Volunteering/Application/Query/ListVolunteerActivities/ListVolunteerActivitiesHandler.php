<?php

declare(strict_types=1);

namespace App\Volunteering\Application\Query\ListVolunteerActivities;

use App\IdentityAccess\Application\Contract\PermissionChecker;
use App\Volunteering\Application\ActivityRegistry;
use App\Volunteering\Application\Contract\SourceActivity;
use App\Volunteering\Application\ServingPermissions;
use App\Volunteering\Application\ServingViews;

final readonly class ListVolunteerActivitiesHandler
{
    public function __construct(
        private PermissionChecker $auth,
        private ActivityRegistry $activities,
    ) {
    }

    /** @return list<SourceActivity> */
    public function handle(ListVolunteerActivitiesQuery $query): array
    {
        $this->auth->requirePermission(ServingPermissions::READ);
        [$from, $until] = ServingViews::range($query->from, $query->until);
        ServingViews::page($query->limit, $query->offset);

        return $this->activities->upcoming($query->kind, $from, $until, $query->limit, $query->offset);
    }
}
