<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Sessions\Application\Query\ListPlanningWorkers;

use App\ChildrenMinistry\Sessions\Application\PlanningWorkerList;
use App\ChildrenMinistry\Sessions\Application\TeamPlanning;
use App\IdentityAccess\Application\Contract\PermissionChecker;

final readonly class ListPlanningWorkersHandler
{
    public function __construct(private PermissionChecker $authorization, private TeamPlanning $planning)
    {
    }

    public function handle(ListPlanningWorkersQuery $query): PlanningWorkerList
    {
        $this->authorization->requirePermission('children.manage');

        return $this->planning->workers($query->search, $query->limit, $query->offset);
    }
}
