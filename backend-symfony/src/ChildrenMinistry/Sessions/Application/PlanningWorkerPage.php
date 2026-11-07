<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Sessions\Application;

use App\ChildrenMinistry\Sessions\Domain\Worker;

final readonly class PlanningWorkerPage
{
    /** @param list<Worker> $values */
    public function __construct(public array $values, public int $total)
    {
    }
}
