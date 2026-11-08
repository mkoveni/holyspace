<?php

declare(strict_types=1);

namespace App\Volunteering\Application\Query\GetAssignmentHistory;

use App\IdentityAccess\Application\Contract\PermissionChecker;
use App\Volunteering\Application\Contract\AssignmentEvent;
use App\Volunteering\Application\Contract\RosterDirectory;
use App\Volunteering\Application\ServingPermissions;
use App\Volunteering\Application\ServingViews;

final readonly class GetAssignmentHistoryHandler
{
    public function __construct(
        private PermissionChecker $auth,
        private RosterDirectory $roster,
    ) {
    }

    /** @return list<AssignmentEvent> */
    public function handle(GetAssignmentHistoryQuery $query): array
    {
        $this->auth->requirePermission(ServingPermissions::READ);
        ServingViews::page($query->limit, $query->offset);

        return $this->roster->events($query->assignmentId, $query->limit, $query->offset);
    }
}
