<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Sessions\Application\Command\ApplyTeamPlan;

use App\ChildrenMinistry\Sessions\Application\TeamPlanDraft;
use App\Shared\Application\Command;

/** @implements Command<null> */
final readonly class ApplyTeamPlanCommand implements Command
{
    public function __construct(public string $actorId, public TeamPlanDraft $draft, public string $reason)
    {
    }
}
