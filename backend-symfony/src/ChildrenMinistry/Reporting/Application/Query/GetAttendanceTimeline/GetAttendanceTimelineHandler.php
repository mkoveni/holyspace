<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Reporting\Application\Query\GetAttendanceTimeline;

use App\ChildrenMinistry\Reporting\Application\AttendanceReport;
use App\ChildrenMinistry\Reporting\Application\AttendanceReporting;
use App\IdentityAccess\Application\Contract\PermissionChecker;

final readonly class GetAttendanceTimelineHandler
{
    public function __construct(private PermissionChecker $authorization, private AttendanceReporting $reporting)
    {
    }

    public function handle(GetAttendanceTimelineQuery $query): AttendanceReport
    {
        $this->authorization->requirePermission('children.read');

        return $this->reporting->timeline($query->attendanceId, $query->limit, $query->offset);
    }
}
