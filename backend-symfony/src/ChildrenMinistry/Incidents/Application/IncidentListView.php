<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Incidents\Application;

final readonly class IncidentListView
{
    /** @param list<IncidentRow> $incidents */
    public function __construct(public array $incidents, public int $total, public int $overdue, public int $unassigned)
    {
    }
}
