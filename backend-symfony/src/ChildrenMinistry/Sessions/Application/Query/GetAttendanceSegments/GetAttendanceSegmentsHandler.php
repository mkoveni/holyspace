<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Sessions\Application\Query\GetAttendanceSegments;

use App\ChildrenMinistry\Sessions\Application\Contract\AttendanceReader;
use App\ChildrenMinistry\Sessions\Domain\AttendanceSegment;
use App\IdentityAccess\Application\Contract\PermissionChecker;

final readonly class GetAttendanceSegmentsHandler
{
    public function __construct(private PermissionChecker $authorization, private AttendanceReader $attendance)
    {
    }

    /** @return list<AttendanceSegment> */
    public function handle(GetAttendanceSegmentsQuery $query): array
    {
        $this->authorization->requirePermission('children.read');

        return $this->attendance->segments($query->attendanceId);
    }
}
