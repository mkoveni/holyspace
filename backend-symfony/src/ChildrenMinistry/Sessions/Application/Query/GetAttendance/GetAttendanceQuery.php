<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Sessions\Application\Query\GetAttendance;

final readonly class GetAttendanceQuery
{
    public function __construct(public string $actorId, public string $attendanceId)
    {
    }
}
