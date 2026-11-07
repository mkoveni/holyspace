<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Incidents\Application;

final readonly class IncidentAttendanceView
{
    /** @param list<IncidentAttendanceRow> $visits */
    public function __construct(public array $visits, public int $total)
    {
    }
}
