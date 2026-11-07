<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Sessions\Application;

use App\ChildrenMinistry\Sessions\Application\Contract\PlanningSessionView;
use DateTimeImmutable;

final readonly class TeamPlanPreview
{
    /**
     * @param list<PlanningSessionView> $sessions
     * @param list<string>              $blockers
     */
    public function __construct(public array $sessions, public array $blockers, public DateTimeImmutable $checkedAt)
    {
    }
}
