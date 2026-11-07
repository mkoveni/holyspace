<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Sessions\Application\Contract;

use DateTimeImmutable;

final readonly class TeamPlanningView
{
    /** @param list<PlanningSessionView> $sessions */
    public function __construct(public array $sessions, public int $total, public PlanningSummary $summary, public string $timeZone, public DateTimeImmutable $checkedAt)
    {
    }
}
