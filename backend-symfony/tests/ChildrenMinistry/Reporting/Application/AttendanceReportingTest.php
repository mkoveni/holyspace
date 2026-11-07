<?php

declare(strict_types=1);

namespace App\Tests\ChildrenMinistry\Reporting\Application;

use App\ChildrenMinistry\Reporting\Application\AttendanceCriteria;
use App\ChildrenMinistry\Reporting\Application\AttendancePage;
use App\ChildrenMinistry\Reporting\Application\AttendanceReportFilter;
use App\ChildrenMinistry\Reporting\Application\AttendanceReporting;
use App\ChildrenMinistry\Reporting\Application\Contract\AttendanceReportReader;
use App\ChildrenMinistry\Reporting\Application\ReportSummary;
use App\ChildrenMinistry\Reporting\Application\ReportVisit;
use App\ChildrenMinistry\Shared\Domain\ChildrenException;
use App\People\Application\Contract\PersonDirectory;
use App\People\Application\Contract\PersonDirectoryEntry;
use App\Shared\Application\ChurchCalendar;
use App\Shared\Domain\CalendarDate;
use Closure;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

final class AttendanceReportingTest extends TestCase
{
    private function reporting(int $visits, int &$nameLookups = 0, ?AttendanceCriteria &$seen = null): AttendanceReporting
    {
        $at = new DateTimeImmutable('2026-06-07T09:00:00Z');
        $page = new AttendancePage(array_fill(0, $visits, new ReportVisit('i', 'a', 'c', '', 's', 'p', 'P', 'C', 'R', 'L', 'UTC', $at, null, 'ATTENDING', false)), new ReportSummary($visits, 1, $visits, 0, 0, 0));
        $record = static function (AttendanceCriteria $criteria) use (&$seen): void {
            $seen = $criteria;
        };
        $reader = new class ($page, $record) implements AttendanceReportReader {
            public function __construct(private AttendancePage $page, private Closure $record)
            {
            }

            public function read(AttendanceCriteria $criteria, int $limit, int $offset): AttendancePage
            {
                ($this->record)($criteria);

                return new AttendancePage(array_slice($this->page->visits, $offset, $limit), $this->page->summary);
            }
        };
        $people = new class ($nameLookups) implements PersonDirectory {
            public function __construct(private int &$lookups)
            {
            }

            public function getAll(array $ids): array
            {
                ++$this->lookups;

                return array_map(static fn (string $id): PersonDirectoryEntry => new PersonDirectoryEntry($id, 'Child Mabasa', null, true), $ids);
            }

            public function matchingIds(string $search): array
            {
                return ['c'];
            }

            public function get(string $id): ?PersonDirectoryEntry
            {
                return null;
            }

            public function lock(string $id): ?PersonDirectoryEntry
            {
                return null;
            }
        };
        $clock = new MockClock('2026-06-07 09:00:00 UTC');

        return new AttendanceReporting($reader, $people, new ChurchCalendar($clock, 'Africa/Johannesburg'), $clock);
    }

    private function filter(string $from = '2026-06-07', string $until = '2026-06-07', string $outcome = 'ALL', string $search = ''): AttendanceReportFilter
    {
        return new AttendanceReportFilter(CalendarDate::parse($from), CalendarDate::parse($until), null, null, null, $search, $outcome);
    }

    public function testOversizedExportsAreRejectedBeforeLoadingNames(): void
    {
        $lookups = 0;
        $reporting = $this->reporting(10001, $lookups);

        try {
            $reporting->export($this->filter());
            self::fail('Expected a rejection.');
        } catch (ChildrenException $invalid) {
            self::assertStringContainsString('10,000', $invalid->getMessage());
        }
        self::assertSame(0, $lookups);
    }

    public function testAnExportOfExactlyTenThousandIsAllowed(): void
    {
        self::assertCount(10000, $this->reporting(10000)->export($this->filter())->visits);
    }

    public function testDatesAreChurchLocalInclusiveAndBounded(): void
    {
        $seen = null;
        $this->reporting(1, seen: $seen)->report($this->filter(), 25, 0);

        self::assertNotNull($seen);
        self::assertSame(['2026-06-06T22:00:00Z', '2026-06-07T22:00:00Z'], [$seen->from?->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z'), $seen->until?->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z')]);
        foreach ([['2026-06-08', '2026-06-07'], ['2025-06-01', '2026-06-07']] as [$from, $until]) {
            try {
                $this->reporting(1)->report($this->filter($from, $until), 25, 0);
                self::fail('Expected a rejection.');
            } catch (ChildrenException $invalid) {
                self::assertSame('Choose a date range of no more than 366 days.', $invalid->getMessage());
            }
        }
        self::assertCount(1, $this->reporting(1)->report($this->filter('2025-06-07', '2026-06-07'), 25, 0)->visits, '366 days inclusive is allowed');
    }

    public function testOutcomeAndPagingAreValidatedAndNamesAreAddedAfterwards(): void
    {
        foreach ([[$this->filter(outcome: 'LOST'), 25], [$this->filter(), 0], [$this->filter(), 101]] as [$filter, $limit]) {
            try {
                $this->reporting(1)->report($filter, $limit, 0);
                self::fail('Expected a rejection.');
            } catch (ChildrenException) {
                self::addToAssertionCount(1);
            }
        }
        $seen = null;
        $report = $this->reporting(2, seen: $seen)->report($this->filter(search: 'child'), 1, 0);

        self::assertSame(['c'], $seen?->matchingChildIds);
        self::assertSame([1, 2, 'Child Mabasa', 'Africa/Johannesburg'], [count($report->visits), $report->summary->visits, $report->visits[0]->childName, $report->timeZone]);
    }

    public function testATimelineForAnUnknownAttendanceIsNotFound(): void
    {
        $this->expectExceptionMessage('Attendance was not found.');
        $this->reporting(0)->timeline('00000000-0000-4000-8000-000000000001', 50, 0);
    }
}
