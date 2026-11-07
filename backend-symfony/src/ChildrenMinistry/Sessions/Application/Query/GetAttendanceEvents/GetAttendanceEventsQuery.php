<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Sessions\Application\Query\GetAttendanceEvents;

final readonly class GetAttendanceEventsQuery
{
    public function __construct(public string $attendanceId, public int $limit, public int $offset)
    {
    }
}
