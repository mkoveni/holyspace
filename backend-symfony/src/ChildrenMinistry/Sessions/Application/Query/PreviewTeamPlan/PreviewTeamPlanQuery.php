<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Sessions\Application\Query\PreviewTeamPlan;

use App\ChildrenMinistry\Sessions\Application\TeamPlanDraft;

final readonly class PreviewTeamPlanQuery
{
    public function __construct(public TeamPlanDraft $draft)
    {
    }
}
