<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Incidents\Application\Contract;

final readonly class IncidentCountSummary
{
    public function __construct(public int $unresolved, public int $overdue, public int $unassigned)
    {
    }
}
