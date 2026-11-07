<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Reporting\Application\Query\ExportAttendanceReport;

use App\ChildrenMinistry\Reporting\Application\AttendanceReport;
use App\ChildrenMinistry\Reporting\Application\AttendanceReporting;
use App\IdentityAccess\Application\Contract\PermissionChecker;

/** Exporting every matching visit needs both read and manage; larger than 10,000 visits is refused, never truncated. */
final readonly class ExportAttendanceReportHandler
{
    public function __construct(private PermissionChecker $authorization, private AttendanceReporting $reporting)
    {
    }

    public function handle(ExportAttendanceReportQuery $query): AttendanceReport
    {
        $this->authorization->requirePermission('children.read');
        $this->authorization->requirePermission('children.manage');

        return $this->reporting->export($query->filter);
    }
}
