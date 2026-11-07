<?php

declare(strict_types=1);

namespace App\Tests\Events\Application;

use App\Events\Application\AttendanceReports;
use App\Events\Application\Contract\AttendanceCounts;
use App\Events\Application\Contract\EventReportReader;
use App\Events\Application\Contract\ReportRow;
use App\Events\Application\Contract\ReportTotals;
use App\Events\Domain\EventException;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

final class AttendanceReportsTest extends TestCase
{
    private function reader(string $title, int $occurrences = 1): EventReportReader
    {
        return new class ($title, $occurrences) implements EventReportReader {
            public function __construct(private readonly string $title, private readonly int $occurrences)
            {
            }

            public function rows(?string $eventId, string $search, DateTimeImmutable $from, DateTimeImmutable $until, DateTimeImmutable $now, int $limit, int $offset): array
            {
                return [new ReportRow('00000000-0000-4000-8000-0000000000c1', '00000000-0000-4000-8000-0000000000e1', $this->title, new DateTimeImmutable('2026-07-05T08:00:00Z'), new DateTimeImmutable('2026-07-05T09:30:00.5Z'), 'Africa/Johannesburg', 'Main "hall"', 'SCHEDULED', new AttendanceCounts(3, 1, 2, 2, 1))];
            }

            public function totals(?string $eventId, string $search, DateTimeImmutable $from, DateTimeImmutable $until, DateTimeImmutable $now): ReportTotals
            {
                return new ReportTotals($this->occurrences, 3, 1, 2, 2, 1);
            }
        };
    }

    private function export(EventReportReader $reader): string
    {
        return (new AttendanceReports($reader, new MockClock('2026-07-06 00:00:00 UTC')))
            ->export(null, '', new DateTimeImmutable('2026-07-01T00:00:00Z'), new DateTimeImmutable('2026-07-31T00:00:00Z'));
    }

    public function testTheCsvHasAHeaderQuotedCellsAndWindowsLineEndings(): void
    {
        $csv = $this->export($this->reader('Sunday service'));

        self::assertSame(
            "Event,Occurrence,Starts at,Ends at,Time zone,Venue,Status,Confirmed,Waitlisted,Cancelled,Attended,Absent\r\n"
            ."\"Sunday service\",\"00000000-0000-4000-8000-0000000000c1\",\"2026-07-05T08:00:00Z\",\"2026-07-05T09:30:00.500Z\",\"Africa/Johannesburg\",\"Main \"\"hall\"\"\",\"SCHEDULED\",3,1,2,2,1\r\n",
            $csv,
        );
    }

    /** @return iterable<string, array{string, string}> */
    public static function formulas(): iterable
    {
        yield 'equals' => ['=SUM(A1)', '"\'=SUM(A1)"'];
        yield 'plus' => ['+1', '"\'+1"'];
        yield 'minus' => ['-1', '"\'-1"'];
        yield 'at' => ['@cmd', '"\'@cmd"'];
        yield 'leading space' => [' =1', '"\' =1"'];
        yield 'plain' => ['Service', '"Service"'];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('formulas')]
    public function testSpreadsheetFormulasAreDefused(string $title, string $cell): void
    {
        self::assertStringContainsString("\r\n".$cell.',"00000000', $this->export($this->reader($title)));
    }

    public function testAnEmptyRangeExportsOnlyTheHeader(): void
    {
        $reader = new class () implements EventReportReader {
            public function rows(?string $eventId, string $search, DateTimeImmutable $from, DateTimeImmutable $until, DateTimeImmutable $now, int $limit, int $offset): array
            {
                return [];
            }

            public function totals(?string $eventId, string $search, DateTimeImmutable $from, DateTimeImmutable $until, DateTimeImmutable $now): ReportTotals
            {
                return new ReportTotals(0, 0, 0, 0, 0, 0);
            }
        };

        self::assertStringEndsWith("Attended,Absent\r\n", $this->export($reader));
    }

    public function testMoreThanTenThousandOccurrencesCannotBeExported(): void
    {
        $this->expectException(EventException::class);
        $this->expectExceptionMessage('Export at most 10,000 occurrences. Choose a shorter range.');

        $this->export($this->reader('Service', 10001));
    }

    public function testTheRangeAndPageAreBounded(): void
    {
        $reports = new AttendanceReports($this->reader('Service'), new MockClock('2026-07-06 00:00:00 UTC'));
        $from = new DateTimeImmutable('2026-01-01T00:00:00Z');
        foreach ([[$from->modify('+367 days'), 25], [$from, 25], [$from->modify('+30 days'), 0], [$from->modify('+30 days'), 101]] as [$until, $limit]) {
            try {
                $reports->report(null, '', $from, $until, $limit, 0);
                self::fail('Expected a rejection.');
            } catch (EventException) {
                $this->addToAssertionCount(1);
            }
        }
    }
}
