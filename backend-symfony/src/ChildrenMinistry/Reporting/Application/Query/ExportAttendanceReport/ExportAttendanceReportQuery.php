<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Reporting\Application\Query\ExportAttendanceReport;

use App\ChildrenMinistry\Reporting\Application\AttendanceReportFilter;

final readonly class ExportAttendanceReportQuery
{
    public function __construct(public AttendanceReportFilter $filter)
    {
    }
}
