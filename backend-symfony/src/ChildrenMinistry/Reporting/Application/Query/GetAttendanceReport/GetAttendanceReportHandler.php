<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Reporting\Application\Query\GetAttendanceReport;

use App\ChildrenMinistry\Reporting\Application\AttendanceReport;
use App\ChildrenMinistry\Reporting\Application\AttendanceReporting;
use App\IdentityAccess\Application\Contract\PermissionChecker;

final readonly class GetAttendanceReportHandler
{
    public function __construct(private PermissionChecker $authorization, private AttendanceReporting $reporting)
    {
    }

    public function handle(GetAttendanceReportQuery $query): AttendanceReport
    {
        $this->authorization->requirePermission('children.read');

        return $this->reporting->report($query->filter, $query->limit, $query->offset);
    }
}
