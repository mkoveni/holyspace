<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Sessions\Application;

/** A visit with the child's name, for station staff who have no People directory permission. */
final readonly class StationAttendance
{
    public function __construct(public AttendanceView $attendance, public string $childName)
    {
    }
}
