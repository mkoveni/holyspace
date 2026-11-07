<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Incidents\Application\Contract;

final readonly class IncidentPage
{
    /** @param list<IncidentSummary> $incidents */
    public function __construct(public array $incidents, public int $total, public int $overdue, public int $unassigned)
    {
    }
}
