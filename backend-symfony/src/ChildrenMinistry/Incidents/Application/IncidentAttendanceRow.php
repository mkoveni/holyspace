<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Incidents\Application;

final readonly class IncidentAttendanceRow
{
    public function __construct(public IncidentVisit $visit, public string $childName)
    {
    }
}
