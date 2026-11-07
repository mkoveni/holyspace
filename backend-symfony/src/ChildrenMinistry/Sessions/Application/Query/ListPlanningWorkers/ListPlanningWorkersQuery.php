<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Sessions\Application\Query\ListPlanningWorkers;

final readonly class ListPlanningWorkersQuery
{
    public function __construct(public string $search, public int $limit, public int $offset)
    {
    }
}
