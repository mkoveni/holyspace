<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Reporting\Application\Query\GetAttendanceTimeline;

final readonly class GetAttendanceTimelineQuery
{
    public function __construct(public string $attendanceId, public int $limit, public int $offset)
    {
    }
}
