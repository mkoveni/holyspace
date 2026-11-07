<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Sessions\Application;

final readonly class IncidentCounts
{
    public function __construct(public int $unresolved, public int $overdue, public int $unassigned)
    {
    }
}
