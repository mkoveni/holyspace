<?php

declare(strict_types=1);

namespace App\Tests\Giving\Infrastructure;

use App\Giving\Application\Contract\GiftFilter;
use App\Giving\Application\Contract\GivingTotal;
use App\Giving\Application\Contract\LedgerLine;
use App\Giving\Application\GivingReports;
use App\Giving\Domain\Allocation;
use App\Giving\Domain\Gift;
use App\Giving\Domain\GivingException;
use App\Giving\Domain\Money;
use App\Giving\Infrastructure\Persistence\DbalGiftStore;
use App\Giving\Infrastructure\Persistence\DbalGivingLedger;
use App\Giving\Infrastructure\Persistence\DbalGivingReader;
use App\Shared\Domain\CalendarDate;
use App\Tests\Giving\Application\FakePeople;
use App\Tests\Support\DatabaseTestCase;
use DateTimeImmutable;
use Symfony\Component\Clock\MockClock;

/** Ledger lines and totals against the real schema: gifts by received date, reversals and refunds by their own date. */
final class GivingLedgerTest extends DatabaseTestCase
{
    private const string ACTOR = '99999999-9999-4999-8999-999999999999';
    private const string GENERAL = '00000000-0000-4000-8000-000000000001';
    private const string MISSIONS = '00000000-0000-4000-8000-000000000002';
    private const string SPLIT = '00000000-0000-4000-8000-0000000000a1';
    private const string ONLINE = '00000000-0000-4000-8000-0000000000a2';
    private const string DOLLARS = '00000000-0000-4000-8000-0000000000a3';
    private const string DONOR = '00000000-0000-4000-8000-0000000000dd';

    private FakePeople $people;

    protected function setUp(): void
    {
        parent::setUp();
        $this->people = new FakePeople();
        $this->people->add(self::DONOR);
        foreach ([self::GENERAL => 'General', self::MISSIONS => 'Missions'] as $id => $name) {
            $this->connection->insert('giving_funds', ['id' => $id, 'name' => $name, 'description' => '', 'request_hash' => str_repeat('a', 64)]);
        }
        $store = new DbalGiftStore($this->connection);
        $now = new DateTimeImmutable('2026-03-01T08:00:00Z');
        // One transaction, as the command bus runs it: the deferred online-source check needs the source row before commit.
        $this->connection->beginTransaction();

        // A donor's split gift in January, fully reversed in February.
        $split = Gift::record(self::SPLIT, self::DONOR, Money::of(10000, 'ZAR'), CalendarDate::of(2026, 1, 5), 'BANK', '=REF', [
            new Allocation(self::GENERAL, 7000), new Allocation(self::MISSIONS, 3000),
        ], self::ACTOR, $now);
        $store->insert($split, str_repeat('b', 64));
        $store->insertReversal($split->reverse(self::SPLIT, CalendarDate::of(2026, 2, 1), 'Duplicate bank entry', self::ACTOR, $now), str_repeat('c', 64));

        // An anonymous online gift in January with a partial provider refund in February (Payments evidence, inserted directly).
        $store->insert(Gift::record(self::ONLINE, null, Money::of(5000, 'ZAR'), CalendarDate::of(2026, 1, 10), 'ONLINE', null, [
            new Allocation(self::GENERAL, 5000),
        ], self::ACTOR, $now), str_repeat('d', 64));
        $this->connection->insert('giving_payment_sources', ['gift_id' => self::ONLINE, 'charge_id' => '00000000-0000-4000-8000-0000000000c1', 'reference' => 'CHG-1']);
        $this->connection->insert('giving_payment_refunds', [
            'id' => '00000000-0000-4000-8000-0000000000e1', 'gift_id' => self::ONLINE, 'fund_id' => self::GENERAL, 'amount_minor' => 2000,
            'refunded_on' => '2026-02-03', 'reference' => 'RF-1', 'created_by' => self::ACTOR, 'created_at' => '2026-02-03 08:00:00+00',
        ]);

        $store->insert(Gift::record(self::DOLLARS, self::DONOR, Money::of(2000, 'USD'), CalendarDate::of(2026, 2, 2), 'CASH', null, [
            new Allocation(self::GENERAL, 2000),
        ], self::ACTOR, $now), str_repeat('e', 64));
        $this->connection->commit();
    }

    private static function filter(string $from = '2026-01-01', string $to = '2026-02-28', ?string $currency = null, ?string $fund = null, ?string $donor = null): GiftFilter
    {
        return new GiftFilter(CalendarDate::parse($from), CalendarDate::parse($to), $currency, $fund, $donor, 25, 0);
    }

    private function ledger(): DbalGivingLedger
    {
        return new DbalGivingLedger($this->connection, $this->people);
    }

    /** @return list<array{string, string, string, int}> */
    private function lines(GiftFilter $filter): array
    {
        return array_map(
            static fn (LedgerLine $line): array => [$line->date->iso(), $line->kind, $line->fundName, $line->amountMinor],
            $this->ledger()->lines($filter, 5000),
        );
    }

    /** @return list<list<string>> */
    private function totals(GiftFilter $filter): array
    {
        return array_map(
            static fn (GivingTotal $t): array => [$t->currency, $t->grossMinor, $t->reversedMinor, $t->netMinor],
            new DbalGivingReader($this->connection, $this->people)->totals($filter),
        );
    }

    public function testLinesAreSignedAllocationsOrderedByDateGiftKindAndFund(): void
    {
        self::assertSame([
            ['2026-01-05', 'GIFT', 'General', 7000],
            ['2026-01-05', 'GIFT', 'Missions', 3000],
            ['2026-01-10', 'GIFT', 'General', 5000],
            ['2026-02-01', 'REVERSAL', 'General', -7000],
            ['2026-02-01', 'REVERSAL', 'Missions', -3000],
            ['2026-02-02', 'GIFT', 'General', 2000],
            ['2026-02-03', 'REFUND', 'General', -2000],
        ], $this->lines(self::filter()));

        $lines = $this->ledger()->lines(self::filter(), 5000);
        self::assertSame([self::DONOR, 'Donor Mabasa', 'BANK', '=REF', ''], [$lines[0]->donorId, $lines[0]->donorName, $lines[0]->method, $lines[0]->reference, $lines[0]->reason]);
        self::assertSame('Duplicate bank entry', $lines[3]->reason);
        self::assertSame([null, 'Anonymous', null], [$lines[2]->donorId, $lines[2]->donorName, $lines[2]->reference]);
        self::assertSame(['RF-1', 'Provider-completed refund', 'ONLINE'], [$lines[6]->reference, $lines[6]->reason, $lines[6]->method]);
        self::assertSame('USD', $lines[5]->currency);
    }

    public function testFiltersSelectAllocationsByFundCurrencyDonorAndLineDate(): void
    {
        self::assertSame([['2026-01-05', 'GIFT', 'Missions', 3000], ['2026-02-01', 'REVERSAL', 'Missions', -3000]], $this->lines(self::filter(fund: self::MISSIONS)));
        self::assertSame([['2026-02-02', 'GIFT', 'General', 2000]], $this->lines(self::filter(currency: 'USD')));
        self::assertCount(5, $this->lines(self::filter(donor: self::DONOR)), 'the anonymous gift and its refund are not the donor\'s');
        self::assertSame([
            ['2026-02-01', 'REVERSAL', 'General', -7000],
            ['2026-02-01', 'REVERSAL', 'Missions', -3000],
            ['2026-02-02', 'GIFT', 'General', 2000],
            ['2026-02-03', 'REFUND', 'General', -2000],
        ], $this->lines(self::filter('2026-02-01')), 'a reversal or refund in the period counts even when the gift is older');
    }

    public function testUnknownDonorsAreUnavailableNotAnonymous(): void
    {
        $this->people->people = [];

        self::assertSame('Person unavailable', $this->ledger()->lines(self::filter(), 5000)[0]->donorName);
    }

    public function testReportsRejectExcessRowsRatherThanSilentlyTruncating(): void
    {
        self::assertCount(7, $this->ledger()->lines(self::filter(), 7));

        $this->expectException(GivingException::class);
        $this->expectExceptionMessage('This report exceeds 6 allocation lines. Narrow the date range or choose a donor or fund.');
        $this->ledger()->lines(self::filter(), 6);
    }

    public function testTotalsSeparateCurrenciesAndCountReversalsAndRefundsByTheirOwnDate(): void
    {
        self::assertSame([['USD', '2000', '0', '2000'], ['ZAR', '15000', '12000', '3000']], $this->totals(self::filter()));
        self::assertSame([['USD', '2000', '0', '2000'], ['ZAR', '0', '12000', '-12000']], $this->totals(self::filter('2026-02-01')));
        self::assertSame([['ZAR', '15000', '0', '15000']], $this->totals(self::filter(to: '2026-01-31')));
        self::assertSame([['ZAR', '3000', '3000', '0']], $this->totals(self::filter(fund: self::MISSIONS)));
        self::assertSame([['USD', '2000', '0', '2000'], ['ZAR', '10000', '10000', '0']], $this->totals(self::filter(donor: self::DONOR)));
        self::assertSame([], $this->totals(self::filter('2025-01-01', '2025-12-31')));
    }

    public function testReportTotalsMatchTheLedgerTotals(): void
    {
        $report = new GivingReports($this->ledger(), $this->people, new MockClock('2026-03-01 08:00:00 UTC'))->report(self::filter());

        self::assertEquals(new DbalGivingReader($this->connection, $this->people)->totals(self::filter()), $report->totals);
    }

    public function testTotalsAreExactBeyondTheJsonSafeIntegerRange(): void
    {
        $store = new DbalGiftStore($this->connection);
        $this->connection->beginTransaction();
        for ($i = 0; $i < 10; ++$i) {
            $store->insert(Gift::record(\sprintf('00000000-0000-4000-8000-%012d', 900 + $i), null, Money::of(1_000_000_000_000, 'EUR'), CalendarDate::of(2026, 1, 1), 'BANK', null, [
                new Allocation(self::GENERAL, 1_000_000_000_000),
            ], self::ACTOR, new DateTimeImmutable('2026-01-01T08:00:00Z')), str_repeat('f', 64));
        }
        $this->connection->commit();

        self::assertSame(['EUR', '10000000000000', '0', '10000000000000'], $this->totals(self::filter(currency: 'EUR'))[0]);
    }
}
