<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Reporting\Presentation;

use App\ChildrenMinistry\Reporting\Application\AttendanceReport;
use App\ChildrenMinistry\Reporting\Application\ReportVisit;
use App\Shared\Presentation\Json;

/** A spreadsheet-safe CSV: UTF-8 BOM, every cell quoted, and text that would run as a formula is neutralised. */
final class AttendanceReportCsv
{
    private function __construct()
    {
    }

    public static function render(AttendanceReport $report): string
    {
        $header = "Child ID,Child,Programme,Class,Location,Room,Session ID,Attendance ID,Arrival UTC,Departure UTC,Location time zone,Outcome,Supervisor collection\r\n";
        $rows = array_map(static fn (ReportVisit $visit): string => implode(',', array_map(self::cell(...), [
            $visit->childId,
            $visit->childName,
            $visit->programme,
            $visit->ministryClass,
            $visit->location,
            $visit->room,
            $visit->sessionId,
            $visit->attendanceId,
            Json::instant($visit->arrivedAt),
            null === $visit->leftAt ? '' : Json::instant($visit->leftAt),
            $visit->timeZone,
            $visit->outcome,
            $visit->supervisorCollection ? 'true' : 'false',
        ])), $report->visits);

        return "\u{FEFF}".$header.implode("\r\n", $rows).([] === $rows ? '' : "\r\n");
    }

    private static function cell(string $value): string
    {
        $clean = str_replace(["\r", "\n"], ' ', $value);
        $leading = ltrim($clean);
        if ('' !== $leading && str_contains('=+-@', $leading[0])) {
            $clean = "'".$clean;
        }

        return '"'.str_replace('"', '""', $clean).'"';
    }
}
