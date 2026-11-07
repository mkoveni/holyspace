<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Sessions\Application;

use App\ChildrenMinistry\Sessions\Domain\StaffRole;

/** Add one registered worker, or swap them in for another, across a handful of versioned sessions. */
final readonly class TeamPlanDraft
{
    /** @param list<TeamPlanTarget> $targets */
    public function __construct(public array $targets, public string $workerId, public ?string $replaceWorkerId, public StaffRole $role)
    {
    }
}
