<?php

declare(strict_types=1);

namespace App\Tests\Giving\Application;

use App\Giving\Application\GivingHash;
use App\Giving\Application\OnlineGivingService;
use App\Giving\Domain\Allocation;
use App\Giving\Domain\Fund;
use App\Giving\Domain\Gift;
use App\Giving\Domain\GivingException;
use App\Giving\Domain\Money;
use App\Giving\Domain\Pledge;
use App\Giving\Domain\PledgeAssignment;
use App\Shared\Application\ChurchCalendar;
use App\Shared\Domain\CalendarDate;
use App\Shared\Domain\ErrorKind;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

/** The Payments-facing ledger in isolation, as Java's OnlineGivingService: no database, no transaction, no identity context. */
final class OnlineGivingServiceTest extends TestCase
{
    private const string ACTOR = '99999999-9999-4999-8999-999999999999';
    private const string CHARGE = '00000000-0000-4000-8000-0000000000c1';
    private const string REFUND = '00000000-0000-4000-8000-0000000000e1';
    private const string FUND = '00000000-0000-4000-8000-000000000001';
    private const string DONOR = '00000000-0000-4000-8000-0000000000dd';
    private const string PLEDGE_A = '00000000-0000-4000-8000-00000000000a';
    private const string PLEDGE_B = '00000000-0000-4000-8000-00000000000b';
    private const string REFERENCE = 'payfast:live:pf_1';

    private LockLog $log;
    private InMemoryFunds $funds;
    private InMemoryGifts $gifts;
    private InMemoryPledges $pledges;
    private InMemoryOnlineGiving $online;
    private FakePeople $people;
    private RecordingHistory $history;
    private InlineUnitOfWork $transaction;
    private MockClock $clock;

    protected function setUp(): void
    {
        $this->log = new LockLog();
        $this->funds = new InMemoryFunds($this->log);
        $this->gifts = new InMemoryGifts($this->log);
        $this->pledges = new InMemoryPledges($this->log);
        $this->online = new InMemoryOnlineGiving($this->pledges);
        $this->people = new FakePeople($this->log);
        $this->history = new RecordingHistory();
        $this->transaction = new InlineUnitOfWork();
        // Still 1 June in UTC, but already 00:30 on 2 June in Johannesburg: the church's date is the 2nd.
        $this->clock = new MockClock('2026-06-01 22:30:00 UTC');
        $this->funds->funds[self::FUND] = Fund::create(self::FUND, 'General', '');
        $this->people->add(self::DONOR);
    }

    private function ledger(): OnlineGivingService
    {
        return new OnlineGivingService(
            $this->gifts,
            $this->funds,
            $this->online,
            $this->pledges,
            $this->history,
            $this->people,
            $this->transaction,
            new ChurchCalendar($this->clock, 'Africa/Johannesburg'),
            $this->clock,
        );
    }

    private function post(int $amount = 10000, string $receivedOn = '2026-06-01', string $reference = self::REFERENCE, string $fund = self::FUND, string $donor = self::DONOR): string
    {
        return $this->ledger()->post(self::CHARGE, $donor, $fund, $amount, $reference, CalendarDate::parse($receivedOn), self::ACTOR);
    }

    private function refund(int $amount = 2500, string $refundedOn = '2026-06-02', string $reference = 'payfast:live:rf_1', string $gift = self::CHARGE): void
    {
        $this->ledger()->refund(self::REFUND, $gift, $amount, $reference, CalendarDate::parse($refundedOn), self::ACTOR);
    }

    private function assignment(string $id, string $pledgeId, int $amount): void
    {
        $at = new DateTimeImmutable('2026-06-01T08:00:00Z');
        $this->pledges->pledges[$pledgeId] ??= Pledge::create($pledgeId, self::DONOR, self::FUND, Money::of(10000, 'ZAR'), CalendarDate::of(2026, 1, 1), CalendarDate::of(2026, 12, 31), self::ACTOR, $at);
        $this->pledges->assignments[$id] = PledgeAssignment::record($id, $pledgeId, self::CHARGE, self::FUND, self::DONOR, 'ZAR', $amount, self::ACTOR, $at);
    }

    private static function assertFails(ErrorKind $kind, string $message, callable $call): void
    {
        try {
            $call();
            self::fail('Expected: '.$message);
        } catch (GivingException $error) {
            self::assertSame([$kind, $message], [$error->kind(), $error->getMessage()]);
        }
    }

    public function testValidateLocksTheDonorThenTheFundAndReturnsTheName(): void
    {
        self::assertSame('Donor Mabasa', $this->ledger()->validate(self::DONOR, self::FUND)->name);
        self::assertSame(['person:'.self::DONOR, 'fund:'.self::FUND], $this->log->locks);
        self::assertSame(1, $this->transaction->calls);
    }

    public function testValidateRefusesAnUnavailableDonorOrAnInactiveFund(): void
    {
        $this->people->add('00000000-0000-4000-8000-0000000000de', available: false);
        self::assertFails(ErrorKind::Invalid, 'Choose an available donor.', fn () => $this->ledger()->validate('00000000-0000-4000-8000-0000000000de', self::FUND));
        self::assertFails(ErrorKind::Invalid, 'Choose an available donor.', fn () => $this->ledger()->validate('00000000-0000-4000-8000-0000000000ff', self::FUND));
        self::assertFails(ErrorKind::Conflict, 'Choose an active giving fund.', fn () => $this->ledger()->validate(self::DONOR, '00000000-0000-4000-8000-0000000000ff'));
        $this->funds->funds[self::FUND] = $this->funds->funds[self::FUND]->revise(0, 'General', '', false);
        self::assertFails(ErrorKind::Conflict, 'Choose an active giving fund.', fn () => $this->ledger()->validate(self::DONOR, self::FUND));
    }

    public function testPostWritesOneOnlineGiftWithItsSourceAndHistory(): void
    {
        self::assertSame(self::CHARGE, $this->post());

        $gift = $this->gifts->gifts[self::CHARGE];
        self::assertSame([self::DONOR, 'ONLINE', 'ZAR', 10000, '2026-06-01', self::REFERENCE, self::ACTOR], [
            $gift->donorId, $gift->method, $gift->money->currency, $gift->money->amountMinor, $gift->receivedOn->iso(), $gift->externalReference, $gift->createdBy,
        ]);
        self::assertEquals([new Allocation(self::FUND, 10000)], $gift->allocations);
        self::assertSame(
            GivingHash::of('verified-payment', self::CHARGE, self::DONOR, self::FUND, 10000, self::REFERENCE, CalendarDate::of(2026, 6, 1), self::ACTOR),
            $this->gifts->hashes[self::CHARGE],
        );
        self::assertSame([self::CHARGE => ['charge' => self::CHARGE, 'reference' => self::REFERENCE]], $this->online->sources);
        self::assertSame(['GIFT:PAYMENT_POSTED:'.self::CHARGE.':'.self::REFERENCE], $this->history->entries);
        self::assertSame(['person:'.self::DONOR, 'fund:'.self::FUND], $this->log->locks);
        self::assertSame(1, $this->transaction->calls);
    }

    public function testAnIdenticalPostIsIdempotentAndOtherDetailsConflict(): void
    {
        $this->post();
        $this->log->locks = [];

        self::assertSame(self::CHARGE, $this->post());
        self::assertSame([], $this->log->locks, 'a replay takes no locks');
        self::assertCount(1, $this->history->entries);
        self::assertFails(ErrorKind::Conflict, 'This request identifier was already used for different details.', fn () => $this->post(9000));
        self::assertCount(1, $this->gifts->gifts);
    }

    public function testThePostDateIsTheChurchDateAndIsCheckedBeforeAnyLock(): void
    {
        self::assertSame(self::CHARGE, $this->post(receivedOn: '2026-06-02'), "the church's date is already the 2nd");
        $this->gifts->gifts = [];
        $this->gifts->hashes = [];
        $this->log->locks = [];

        self::assertFails(ErrorKind::Invalid, 'Choose a date from 1900 through today.', fn () => $this->post(receivedOn: '2026-06-03'));
        self::assertSame([], $this->log->locks);
    }

    public function testPostNeedsAKnownDonorAndFundButNotAnAvailableOrActiveOne(): void
    {
        self::assertFails(ErrorKind::NotFound, 'The Giving record was not found.', fn () => $this->post(donor: '00000000-0000-4000-8000-0000000000ff'));
        self::assertFails(ErrorKind::NotFound, 'The Giving record was not found.', fn () => $this->post(fund: '00000000-0000-4000-8000-0000000000ff'));
        self::assertSame([], $this->gifts->gifts);

        // Java checks availability and activity before the checkout (validate), not when posting a verified charge.
        $this->people->add(self::DONOR, available: false);
        $this->funds->funds[self::FUND] = $this->funds->funds[self::FUND]->revise(0, 'General', '', false);
        self::assertSame(self::CHARGE, $this->post());
    }

    public function testPostAppliesTheGiftRulesAfterTheLocks(): void
    {
        self::assertFails(ErrorKind::Invalid, 'Enter a positive amount of at most 10,000,000,000.00.', fn () => $this->post(0));
        self::assertSame(['person:'.self::DONOR, 'fund:'.self::FUND], $this->log->locks);
        self::assertSame([], $this->online->sources);
    }

    public function testARefundIsRecordedOnceWithHistory(): void
    {
        $this->post();
        $this->refund();
        $this->refund();

        self::assertCount(1, $this->online->refunds);
        $refund = $this->online->refunds[self::REFUND];
        self::assertSame([self::CHARGE, self::FUND, 2500, 'payfast:live:rf_1', '2026-06-02'], [$refund->giftId, $refund->fundId, $refund->amountMinor, $refund->reference, $refund->refundedOn->iso()]);
        self::assertSame(['GIFT:PAYMENT_POSTED:'.self::CHARGE.':'.self::REFERENCE, 'GIFT:PAYMENT_REFUNDED:'.self::CHARGE.':payfast:live:rf_1'], $this->history->entries);
        self::assertFails(ErrorKind::Conflict, 'Refund identity conflicts with existing evidence.', fn () => $this->refund(2600));
        self::assertFails(ErrorKind::Conflict, 'Refund identity conflicts with existing evidence.', fn () => $this->refund(refundedOn: '2026-06-01'));
    }

    public function testARetriedRefundIsAcceptedBeforeItsDateIsCheckedAgain(): void
    {
        $this->post();
        $this->refund(refundedOn: '2026-06-02');
        $this->clock->modify('-1 day');

        $this->refund(refundedOn: '2026-06-02');
        self::assertCount(1, $this->online->refunds);
    }

    public function testARefundNeedsTheGiftAndMatchesItsAmountAndDates(): void
    {
        self::assertFails(ErrorKind::NotFound, 'The Giving record was not found.', fn () => $this->refund());
        $this->post();
        $this->gifts->refunds[self::CHARGE] = 7000;

        self::assertFails(ErrorKind::Invalid, 'Choose a date from 1900 through today.', fn () => $this->refund(refundedOn: '2026-06-03'));
        self::assertFails(ErrorKind::Conflict, 'Refund must match the unrefunded online gift and its dates.', fn () => $this->refund(3001));
        self::assertFails(ErrorKind::Conflict, 'Refund must match the unrefunded online gift and its dates.', fn () => $this->refund(100, '2026-05-31'));
        self::assertSame([], $this->online->refunds);
        $this->refund(3000);
        self::assertCount(1, $this->online->refunds);
    }

    public function testAManualGiftCannotBeRefundedThroughPayments(): void
    {
        $at = new DateTimeImmutable('2026-06-01T08:00:00Z');
        $this->gifts->insert(Gift::record(self::CHARGE, null, Money::of(10000, 'ZAR'), CalendarDate::of(2026, 6, 1), 'BANK', null, [new Allocation(self::FUND, 10000)], self::ACTOR, $at), 'h');

        self::assertFails(ErrorKind::Conflict, 'Refund must match the unrefunded online gift and its dates.', fn () => $this->refund());
    }

    public function testARefundCancelsTheGiftsActiveAssignmentsLockingPledgesInIdOrder(): void
    {
        $this->post();
        $this->assignment('00000000-0000-4000-8000-0000000000f3', self::PLEDGE_B, 1000);
        $this->assignment('00000000-0000-4000-8000-0000000000f1', self::PLEDGE_A, 2000);
        $this->assignment('00000000-0000-4000-8000-0000000000f2', self::PLEDGE_A, 3000);
        $this->log->locks = [];
        $this->history->entries = [];

        $this->refund();

        self::assertSame(['gift:'.self::CHARGE, 'pledge:'.self::PLEDGE_A, 'pledge:'.self::PLEDGE_B], $this->log->locks);
        foreach ($this->pledges->assignments as $assignment) {
            self::assertSame([PledgeAssignment::CANCELLED, 1, OnlineGivingService::REFUND_REVIEW, self::ACTOR], [
                $assignment->status, $assignment->version, $assignment->cancellationReason, $assignment->cancelledBy,
            ]);
        }
        self::assertSame([1, 1], [$this->pledges->pledges[self::PLEDGE_A]->version, $this->pledges->pledges[self::PLEDGE_B]->version]);
        self::assertSame([
            'PLEDGE:PAYMENT_REFUND:'.self::PLEDGE_A.':payfast:live:rf_1',
            'PLEDGE:PAYMENT_REFUND:'.self::PLEDGE_B.':payfast:live:rf_1',
            'GIFT:PAYMENT_REFUNDED:'.self::CHARGE.':payfast:live:rf_1',
        ], $this->history->entries);
    }

    public function testARejectedRefundCancelsNoAssignment(): void
    {
        $this->post();
        $this->assignment('00000000-0000-4000-8000-0000000000f1', self::PLEDGE_A, 2000);

        self::assertFails(ErrorKind::Conflict, 'Refund must match the unrefunded online gift and its dates.', fn () => $this->refund(10001));
        self::assertSame(PledgeAssignment::ACTIVE, $this->pledges->assignments['00000000-0000-4000-8000-0000000000f1']->status);
        self::assertSame(0, $this->pledges->pledges[self::PLEDGE_A]->version);
    }
}
