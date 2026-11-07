<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Sessions\Application\Query\GetAttendanceSegments;

final readonly class GetAttendanceSegmentsQuery
{
    public function __construct(public string $attendanceId)
    {
    }
}
