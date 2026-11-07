<?php

declare(strict_types=1);

namespace App\Tests\Giving\Infrastructure;

use App\Giving\Domain\Allocation;
use App\Giving\Domain\Gift;
use App\Giving\Domain\GivingException;
use App\Giving\Domain\Money;
use App\Giving\Domain\OnlineRefund;
use App\Giving\Domain\PledgeAssignment;
use App\Giving\Infrastructure\Persistence\DbalGiftStore;
use App\Giving\Infrastructure\Persistence\DbalOnlineGivingStore;
use App\Shared\Domain\CalendarDate;
use App\Tests\Support\DatabaseTestCase;
use DateTimeImmutable;
use Doctrine\DBAL\Exception\DriverException;

/** Payments evidence against the real schema: the online-source, refund-guard and online-reversal triggers the port relies on. */
final class OnlineGivingStoreTest extends DatabaseTestCase
{
    private const string ACTOR = '99999999-9999-4999-8999-999999999999';
    private const string FUND = '00000000-0000-4000-8000-000000000001';
    private const string GIFT = '00000000-0000-4000-8000-0000000000c1';
    private const string MANUAL = '00000000-0000-4000-8000-0000000000b1';
    private const string DONOR = '00000000-0000-4000-8000-0000000000dd';

    private DbalGiftStore $gifts;
    private DbalOnlineGivingStore $online;
    private DateTimeImmutable $now;

    protected function setUp(): void
    {
        parent::setUp();
        $this->gifts = new DbalGiftStore($this->connection);
        $this->online = new DbalOnlineGivingStore($this->connection);
        $this->now = new DateTimeImmutable('2026-03-01T08:00:00Z');
        $this->connection->insert('giving_funds', ['id' => self::FUND, 'name' => 'General', 'description' => '', 'request_hash' => str_repeat('a', 64)]);
    }

    private function online(string $id = self::GIFT, string $reference = 'payfast:live:charge_1'): Gift
    {
        return Gift::online($id, self::DONOR, self::FUND, 10000, $reference, CalendarDate::of(2026, 2, 1), self::ACTOR, $this->now);
    }

    private function posted(): Gift
    {
        $gift = $this->online();
        $this->connection->transactional(function () use ($gift): void {
            $this->gifts->insert($gift, str_repeat('b', 64));
            $this->online->source($gift->id, $gift->id, 'payfast:live:charge_1');
        });

        return $gift;
    }

    private function refund(Gift $gift, string $id, int $amount, string $reference, string $date = '2026-02-10'): OnlineRefund
    {
        return $gift->refundOnline($id, $amount, $reference, CalendarDate::parse($date), 0, self::ACTOR, $this->now);
    }

    private static function assertEvidenceConflict(callable $write): void
    {
        try {
            $write();
            self::fail('Expected an evidence conflict');
        } catch (GivingException $error) {
            self::assertSame(GivingException::evidenceConflict()->getMessage(), $error->getMessage());
        }
    }

    public function testAnOnlineGiftNeedsItsPaymentSourceBeforeCommit(): void
    {
        $this->connection->beginTransaction();
        $this->gifts->insert($this->online(), str_repeat('b', 64));
        try {
            $this->connection->commit();
            self::fail('The deferred online-source trigger must reject the commit');
        } catch (DriverException $error) {
            self::assertSame('23514', $error->getSQLState());
        }
        self::assertSame(0, (int) $this->connection->fetchOne('SELECT count(*) FROM giving_gifts'));
    }

    public function testTheSourceIsCheckedAtOnceAndChargesAndReferencesAreUnique(): void
    {
        $this->posted();
        self::assertSame(1, (int) $this->connection->fetchOne('SELECT count(*) FROM giving_payment_sources'));

        $this->connection->beginTransaction();
        $this->gifts->insert($this->online('00000000-0000-4000-8000-0000000000c2', 'payfast:live:charge_2'), str_repeat('c', 64));
        self::assertEvidenceConflict(fn () => $this->online->source('00000000-0000-4000-8000-0000000000c2', self::GIFT, 'payfast:live:charge_2'));
        $this->connection->rollBack();

        $this->connection->beginTransaction();
        $this->gifts->insert($this->online('00000000-0000-4000-8000-0000000000c3', 'payfast:live:charge_3'), str_repeat('d', 64));
        self::assertEvidenceConflict(fn () => $this->online->source('00000000-0000-4000-8000-0000000000c3', '00000000-0000-4000-8000-0000000000c3', 'payfast:live:charge_1'));
        $this->connection->rollBack();
    }

    public function testTwoOnlineGiftsCanBePostedInOneTransaction(): void
    {
        $this->connection->transactional(function (): void {
            foreach (['00000000-0000-4000-8000-0000000000c4' => 'payfast:live:a', '00000000-0000-4000-8000-0000000000c5' => 'payfast:live:b'] as $id => $reference) {
                $this->gifts->insert($this->online($id, $reference), str_repeat('e', 64));
                $this->online->source($id, $id, $reference);
            }
        });
        self::assertSame(2, (int) $this->connection->fetchOne('SELECT count(*) FROM giving_payment_sources'));
    }

    public function testRefundsAreImmutableAndTheirIdentityIsChecked(): void
    {
        $gift = $this->posted();
        $this->online->refund($this->refund($gift, '00000000-0000-4000-8000-0000000000e1', 2500, 'payfast:live:refund_1'));

        self::assertTrue($this->online->refundExists('00000000-0000-4000-8000-0000000000e1', self::GIFT, 2500, 'payfast:live:refund_1', CalendarDate::of(2026, 2, 10)));
        self::assertFalse($this->online->refundExists('00000000-0000-4000-8000-0000000000e2', self::GIFT, 2500, 'payfast:live:refund_1', CalendarDate::of(2026, 2, 10)));
        foreach ([[2500, 'payfast:live:refund_1', '2026-02-11'], [2400, 'payfast:live:refund_1', '2026-02-10'], [2500, 'payfast:live:other', '2026-02-10']] as [$amount, $reference, $date]) {
            try {
                $this->online->refundExists('00000000-0000-4000-8000-0000000000e1', self::GIFT, $amount, $reference, CalendarDate::parse($date));
                self::fail('A reused refund id with other details must conflict');
            } catch (GivingException $error) {
                self::assertSame('Refund identity conflicts with existing evidence.', $error->getMessage());
            }
        }
        foreach (['UPDATE giving_payment_refunds SET amount_minor = 1', 'DELETE FROM giving_payment_refunds', 'DELETE FROM giving_payment_sources'] as $sql) {
            try {
                $this->connection->executeStatement($sql);
                self::fail("$sql must be rejected");
            } catch (DriverException $error) {
                self::assertSame('23514', $error->getSQLState(), $sql);
            }
        }
    }

    public function testTheRefundGuardTriggerIsTheBackstopAndIsTranslated(): void
    {
        $gift = $this->posted();
        $this->online->refund($this->refund($gift, '00000000-0000-4000-8000-0000000000e1', 6000, 'payfast:live:refund_1'));

        // Each would pass Gift::refundOnline with a stale refunded amount; the trigger re-checks against the stored rows.
        self::assertEvidenceConflict(fn () => $this->online->refund($this->refund($gift, '00000000-0000-4000-8000-0000000000e2', 4001, 'payfast:live:refund_2')));
        self::assertEvidenceConflict(fn () => $this->online->refund($this->refund($gift, '00000000-0000-4000-8000-0000000000e3', 100, 'payfast:live:refund_1')));
        self::assertEvidenceConflict(fn () => $this->online->refund($this->refund($gift, '00000000-0000-4000-8000-0000000000e4', 100, 'payfast:live:'.str_repeat('r', 200))));
        self::assertSame(1, (int) $this->connection->fetchOne('SELECT count(*) FROM giving_payment_refunds'));
    }

    public function testManualGiftsHaveNoPaymentRefundsAndOnlineGiftsNoManualReversal(): void
    {
        $manual = Gift::record(self::MANUAL, null, Money::of(10000, 'ZAR'), CalendarDate::of(2026, 2, 1), 'BANK', null, [new Allocation(self::FUND, 10000)], self::ACTOR, $this->now);
        $this->connection->transactional(fn () => $this->gifts->insert($manual, str_repeat('f', 64)));
        $refund = $this->refund($this->online(self::MANUAL), '00000000-0000-4000-8000-0000000000e5', 100, 'payfast:live:manual');
        self::assertEvidenceConflict(fn () => $this->online->refund($refund));

        $gift = $this->posted();
        self::assertEvidenceConflict(fn () => $this->gifts->insertReversal($gift->reverse('00000000-0000-4000-8000-0000000000f9', CalendarDate::of(2026, 2, 2), 'Bypass', self::ACTOR, $this->now), str_repeat('9', 64)));
        self::assertSame(0, (int) $this->connection->fetchOne('SELECT count(*) FROM giving_reversals'));
    }

    public function testActiveAssignmentsAreReadInPledgeOrder(): void
    {
        $this->posted();
        foreach (['00000000-0000-4000-8000-00000000000b', '00000000-0000-4000-8000-00000000000a'] as $pledge) {
            $this->connection->insert('giving_pledges', [
                'id' => $pledge, 'donor_id' => self::DONOR, 'fund_id' => self::FUND, 'amount_minor' => 10000, 'currency' => 'ZAR', 'starts_on' => '2026-01-01',
                'ends_on' => '2026-12-31', 'status' => 'OPEN', 'version' => 0, 'request_hash' => str_repeat('a', 64), 'created_by' => self::ACTOR, 'created_at' => '2026-02-01 08:00:00+00',
            ]);
        }
        foreach (['00000000-0000-4000-8000-0000000000f2' => '00000000-0000-4000-8000-00000000000b', '00000000-0000-4000-8000-0000000000f1' => '00000000-0000-4000-8000-00000000000a'] as $id => $pledge) {
            $this->connection->insert('giving_pledge_assignments', [
                'id' => $id, 'pledge_id' => $pledge, 'gift_id' => self::GIFT, 'fund_id' => self::FUND, 'donor_id' => self::DONOR, 'currency' => 'ZAR', 'amount_minor' => 1000,
                'status' => 'ACTIVE', 'version' => 0, 'request_hash' => str_repeat('a', 64), 'created_by' => self::ACTOR, 'created_at' => '2026-02-01 08:00:00+00',
            ]);
        }

        $assignments = $this->online->activeAssignments(self::GIFT);
        self::assertSame(['00000000-0000-4000-8000-00000000000a', '00000000-0000-4000-8000-00000000000b'], array_map(static fn (PledgeAssignment $a): string => $a->pledgeId, $assignments));
        self::assertSame([], $this->online->activeAssignments(self::MANUAL));
    }
}
