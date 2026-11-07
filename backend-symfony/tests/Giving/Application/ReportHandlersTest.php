<?php

declare(strict_types=1);

namespace App\Tests\Giving\Application;

use App\Giving\Application\Contract\GiftFilter;
use App\Giving\Application\Contract\GivingTotal;
use App\Giving\Application\Contract\LedgerLine;
use App\Giving\Application\GivingAccess;
use App\Giving\Application\GivingReports;
use App\Giving\Application\Query\ExportGivingLedger\ExportGivingLedgerHandler;
use App\Giving\Application\Query\ExportGivingLedger\ExportGivingLedgerQuery;
use App\Giving\Application\Query\GetDonorStatement\GetDonorStatementHandler;
use App\Giving\Application\Query\GetDonorStatement\GetDonorStatementQuery;
use App\Giving\Application\Query\GetGivingTotals\GetGivingTotalsHandler;
use App\Giving\Application\Query\GetGivingTotals\GetGivingTotalsQuery;
use App\Giving\Domain\GivingException;
use App\IdentityAccess\Domain\IdentityException;
use App\Shared\Domain\CalendarDate;
use App\Shared\Domain\ErrorKind;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

/** Totals, statement and export handlers in isolation: permission first, filters validated before any read. */
final class ReportHandlersTest extends TestCase
{
    private const string ACTOR = '99999999-9999-4999-8999-999999999999';
    private const string DONOR = '00000000-0000-4000-8000-0000000000dd';
    private const string GIFT = '00000000-0000-4000-8000-0000000000aa';
    private const string FUND = '00000000-0000-4000-8000-000000000001';

    private FakeGivingReader $reader;
    private FakeLedger $ledger;
    private FakePeople $people;
    private RecordingPermissions $permissions;

    protected function setUp(): void
    {
        $this->reader = new FakeGivingReader();
        $this->ledger = new FakeLedger();
        $this->people = new FakePeople();
        $this->people->add(self::DONOR);
    }

    /** @param list<string> $granted */
    private function access(array $granted): GivingAccess
    {
        $this->permissions = new RecordingPermissions(self::ACTOR, $granted);

        return new GivingAccess($this->permissions);
    }

    private function reports(): GivingReports
    {
        return new GivingReports($this->ledger, $this->people, new MockClock('2026-03-01 08:00:00 UTC'));
    }

    private static function filter(?string $from = '2026-01-01', ?string $to = '2026-01-31', ?string $currency = null, int $limit = 25): GiftFilter
    {
        return new GiftFilter(null === $from ? null : CalendarDate::parse($from), null === $to ? null : CalendarDate::parse($to), $currency, null, null, $limit, 0);
    }

    private static function line(string $currency, int $amount, string $kind = 'GIFT'): LedgerLine
    {
        return new LedgerLine(self::GIFT, $kind, CalendarDate::of(2026, 1, 5), null, 'Anonymous', self::FUND, 'General', $currency, $amount, 'BANK', null, '');
    }

    public function testTotalsNeedReadAndAValidFilterBeforeAnyRead(): void
    {
        $denied = new GetGivingTotalsHandler($this->access(['giving.manage']), $this->reader);
        try {
            $denied->handle(new GetGivingTotalsQuery(self::filter(null)));
            self::fail('read is required');
        } catch (IdentityException) {
        }

        $handler = new GetGivingTotalsHandler($this->access(['giving.read']), $this->reader);
        foreach ([self::filter(null), self::filter('2026-02-01'), self::filter('2016-01-01'), self::filter(currency: 'JPY'), self::filter(limit: 101)] as $filter) {
            try {
                $handler->handle(new GetGivingTotalsQuery($filter));
                self::fail('the filter is validated');
            } catch (GivingException $error) {
                self::assertSame(ErrorKind::Invalid, $error->kind());
            }
        }

        $this->reader->totals = [new GivingTotal('ZAR', '1', '0', '1')];
        self::assertSame($this->reader->totals, $handler->handle(new GetGivingTotalsQuery(self::filter())));
        self::assertCount(1, $this->reader->totalCalls, 'rejected filters never reached the reader');
    }

    public function testStatementNeedsReadThenADonorThenAValidPeriod(): void
    {
        $denied = new GetDonorStatementHandler($this->access([]), $this->reports());
        try {
            $denied->handle(new GetDonorStatementQuery(null, null, null));
            self::fail('read is required');
        } catch (IdentityException) {
        }
        self::assertSame(['giving.read'], $this->permissions->checks);

        $handler = new GetDonorStatementHandler($this->access(['giving.read']), $this->reports());
        foreach ([[null, '2026-01-01', 'Choose a donor for the statement.'], [self::DONOR, null, 'Choose a period of at most ten years, with the end on or after the start.']] as [$donor, $from, $message]) {
            try {
                $handler->handle(new GetDonorStatementQuery($donor, null === $from ? null : CalendarDate::parse($from), CalendarDate::of(2026, 1, 31)));
                self::fail($message);
            } catch (GivingException $error) {
                self::assertSame($message, $error->getMessage());
            }
        }

        $report = $handler->handle(new GetDonorStatementQuery(self::DONOR, CalendarDate::of(2026, 1, 1), CalendarDate::of(2026, 1, 31)));
        self::assertCount(1, $this->ledger->calls, 'rejected statements never reached the ledger');
        [$filter, $maximum] = $this->ledger->calls[0];
        self::assertSame([self::DONOR, null, null, 25, 0, 5000], [$filter->donorId, $filter->fundId, $filter->currency, $filter->limit, $filter->offset, $maximum]);
        self::assertSame([self::DONOR, 'Donor Mabasa', '2026-01-01', '2026-01-31'], [$report->donorId, $report->donorName, $report->from->iso(), $report->to->iso()]);
        self::assertEquals(new DateTimeImmutable('2026-03-01 08:00:00 UTC'), $report->generatedAt);

        $this->people->people = [];
        self::assertSame('Person unavailable', $handler->handle(new GetDonorStatementQuery(self::DONOR, CalendarDate::of(2026, 1, 1), CalendarDate::of(2026, 1, 31)))->donorName);
    }

    public function testExportNeedsReadAndExport(): void
    {
        foreach ([[['giving.export'], ['giving.read']], [['giving.read', 'giving.manage'], ['giving.read', 'giving.export']]] as [$granted, $checked]) {
            $handler = new ExportGivingLedgerHandler($this->access($granted), $this->reports());
            try {
                $handler->handle(new ExportGivingLedgerQuery(self::filter(null)));
                self::fail('export needs both permissions');
            } catch (IdentityException) {
            }
            self::assertSame($checked, $this->permissions->checks);
        }

        $report = new ExportGivingLedgerHandler($this->access(['giving.read', 'giving.export']), $this->reports())->handle(new ExportGivingLedgerQuery(self::filter()));
        self::assertCount(1, $this->ledger->calls, 'refused exports never reached the ledger');
        self::assertSame([null, 'All donors'], [$report->donorId, $report->donorName]);
    }

    public function testReportTotalsComeFromTheLinesPerCurrencyInCurrencyOrder(): void
    {
        $this->ledger->lines = [
            self::line('ZAR', 7000),
            self::line('USD', 2000),
            self::line('ZAR', -7000, 'REVERSAL'),
            self::line('ZAR', 3000),
            self::line('ZAR', -500, 'REFUND'),
        ];

        $totals = $this->reports()->report(self::filter())->totals;

        self::assertEquals([new GivingTotal('USD', '2000', '0', '2000'), new GivingTotal('ZAR', '10000', '7500', '2500')], $totals);

        $this->ledger->lines = [];
        self::assertSame([], $this->reports()->report(self::filter())->totals);
    }
}
