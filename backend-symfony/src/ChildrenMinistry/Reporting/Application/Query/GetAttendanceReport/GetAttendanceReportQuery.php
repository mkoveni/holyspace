<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Reporting\Application\Query\GetAttendanceReport;

use App\ChildrenMinistry\Reporting\Application\AttendanceReportFilter;

final readonly class GetAttendanceReportQuery
{
    public function __construct(public AttendanceReportFilter $filter, public int $limit, public int $offset)
    {
    }
}
