<?php

declare(strict_types=1);

namespace App\Events\Application\Query\ExportEventAttendanceReport;

use App\Events\Application\AttendanceReports;
use App\IdentityAccess\Application\Contract\PermissionChecker;

final readonly class ExportEventAttendanceReportHandler
{
    public function __construct(private PermissionChecker $authorization, private AttendanceReports $reports)
    {
    }

    /** @return string the report as CSV */
    public function handle(ExportEventAttendanceReportQuery $query): string
    {
        $this->authorization->requirePermission('events.attendance.read');

        return $this->reports->export($query->eventId, $query->search, $query->from, $query->until);
    }
}
