<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Incidents\Application;

final readonly class IncidentVisits
{
    /** @param list<IncidentVisit> $visits */
    public function __construct(public array $visits, public int $total)
    {
    }
}
