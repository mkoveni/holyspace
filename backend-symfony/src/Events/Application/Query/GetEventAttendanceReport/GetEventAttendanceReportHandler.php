<?php

declare(strict_types=1);

namespace App\Events\Application\Query\GetEventAttendanceReport;

use App\Events\Application\AttendanceReports;
use App\Events\Application\Contract\ReportPage;
use App\IdentityAccess\Application\Contract\PermissionChecker;

final readonly class GetEventAttendanceReportHandler
{
    public function __construct(private PermissionChecker $authorization, private AttendanceReports $reports)
    {
    }

    public function handle(GetEventAttendanceReportQuery $query): ReportPage
    {
        $this->authorization->requirePermission('events.attendance.read');

        return $this->reports->report($query->eventId, $query->search, $query->from, $query->until, $query->limit, $query->offset);
    }
}
