<?php

declare(strict_types=1);

namespace App\Tests\ChildrenMinistry\Reporting\Presentation;

use App\ChildrenMinistry\Reporting\Application\AttendanceReport;
use App\ChildrenMinistry\Reporting\Application\ReportSummary;
use App\ChildrenMinistry\Reporting\Application\ReportVisit;
use App\ChildrenMinistry\Reporting\Presentation\AttendanceReportCsv;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class AttendanceReportCsvTest extends TestCase
{
    public function testCsvQuotesNamesAndNeutralizesSpreadsheetFormulas(): void
    {
        $at = new DateTimeImmutable('1970-01-01T00:00:00Z');
        $visit = new ReportVisit('i', 'i', 'i', '  =SUM(1,2)', 'i', 'i', 'A, "quoted" programme', 'Class', 'Room', 'Campus', 'Africa/Johannesburg', $at, null, 'ATTENDING', false);

        $csv = AttendanceReportCsv::render(new AttendanceReport([$visit], new ReportSummary(1, 1, 1, 0, 0, 0), 'UTC', $at));

        self::assertStringStartsWith("\u{FEFF}Child ID,", $csv);
        self::assertStringContainsString('"\'  =SUM(1,2)"', $csv);
        self::assertStringContainsString('"A, ""quoted"" programme"', $csv);
        self::assertStringEndsWith("\r\n", $csv);
    }

    public function testAnEmptyReportIsJustTheHeader(): void
    {
        $csv = AttendanceReportCsv::render(new AttendanceReport([], new ReportSummary(0, 0, 0, 0, 0, 0), 'UTC', new DateTimeImmutable()));

        self::assertSame(1, substr_count($csv, "\r\n"));
    }

    public function testEveryLeadingFormulaCharacterIsNeutralised(): void
    {
        $at = new DateTimeImmutable('1970-01-01T00:00:00Z');
        foreach (['=1', '+1', '-1', '@x'] as $name) {
            $visit = new ReportVisit('i', 'i', 'i', $name, 'i', 'i', 'P', 'C', 'R', 'L', 'UTC', $at, $at, 'COLLECTED', true);
            self::assertStringContainsString('"\''.$name.'"', AttendanceReportCsv::render(new AttendanceReport([$visit], new ReportSummary(1, 1, 0, 1, 0, 1), 'UTC', $at)));
        }
    }
}
