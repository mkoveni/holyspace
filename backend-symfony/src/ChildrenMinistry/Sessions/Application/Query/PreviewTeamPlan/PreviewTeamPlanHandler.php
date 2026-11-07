<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Sessions\Application\Query\PreviewTeamPlan;

use App\ChildrenMinistry\Sessions\Application\TeamPlanning;
use App\ChildrenMinistry\Sessions\Application\TeamPlanPreview;
use App\IdentityAccess\Application\Contract\PermissionChecker;

final readonly class PreviewTeamPlanHandler
{
    public function __construct(private PermissionChecker $authorization, private TeamPlanning $planning)
    {
    }

    public function handle(PreviewTeamPlanQuery $query): TeamPlanPreview
    {
        $this->authorization->requirePermission('children.manage');

        return $this->planning->preview($query->draft);
    }
}
