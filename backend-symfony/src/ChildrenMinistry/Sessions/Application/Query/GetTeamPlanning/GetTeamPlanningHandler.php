<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Sessions\Application\Query\GetTeamPlanning;

use App\ChildrenMinistry\Sessions\Application\Contract\TeamPlanningView;
use App\ChildrenMinistry\Sessions\Application\TeamPlanning;
use App\IdentityAccess\Application\Contract\PermissionChecker;

final readonly class GetTeamPlanningHandler
{
    public function __construct(private PermissionChecker $authorization, private TeamPlanning $planning)
    {
    }

    public function handle(GetTeamPlanningQuery $query): TeamPlanningView
    {
        $this->authorization->requirePermission('children.manage');

        return $this->planning->list($query->from, $query->until, $query->programmeId, $query->locationId, $query->search, $query->state, $query->limit, $query->offset);
    }
}
