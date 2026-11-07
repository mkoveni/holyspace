<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Sessions\Application;

final readonly class PlanningWorkerList
{
    /** @param list<PlanningWorkerOption> $workers */
    public function __construct(public array $workers, public int $total)
    {
    }
}
