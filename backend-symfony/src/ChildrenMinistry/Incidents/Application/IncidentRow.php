<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Incidents\Application;

use App\ChildrenMinistry\Incidents\Application\Contract\IncidentSummary;

final readonly class IncidentRow
{
    public function __construct(public IncidentSummary $incident, public string $childName)
    {
    }
}
