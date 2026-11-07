<?php

declare(strict_types=1);

namespace App\Tests\Giving\Domain;

use App\Giving\Domain\Allocation;
use App\Giving\Domain\Gift;
use App\Giving\Domain\GivingException;
use App\Giving\Domain\Money;
use App\Giving\Domain\Pledge;
use App\Giving\Domain\PledgeAssignment;
use App\Shared\Domain\CalendarDate;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

/** Java's PledgeTest plus the record-constructor rules. */
final class PledgeTest extends TestCase
{
    private const string PLEDGE = '00000000-0000-4000-8000-0000000000a1';
    private const string DONOR = '00000000-0000-4000-8000-0000000000dd';
    private const string FUND = '00000000-0000-4000-8000-000000000001';
    private const string ACTOR = '99999999-9999-4999-8999-999999999999';
    private const string PERIOD = 'A pledge needs a donor, fund, currency and a commitment period of at most ten years.';

    private function pledge(string $starts = '2026-01-01', string $ends = '2026-12-31'): Pledge
    {
        return Pledge::create(self::PLEDGE, self::DONOR, self::FUND, Money::of(10000, 'ZAR'), CalendarDate::parse($starts), CalendarDate::parse($ends), self::ACTOR, new DateTimeImmutable('2026-01-01T08:00:00Z'));
    }

    private function gift(string $currency = 'ZAR', string $date = '2026-02-01', ?string $donor = self::DONOR): Gift
    {
        return Gift::reconstitute('00000000-0000-4000-8000-0000000000aa', $donor, Money::of(10000, $currency), CalendarDate::parse($date), 'BANK', null, [new Allocation(self::FUND, 10000)], self::ACTOR, new DateTimeImmutable('2026-02-01T08:00:00Z'));
    }

    private function assign(Pledge $pledge, Gift $gift, int $amount, int $credited, int $available): PledgeAssignment
    {
        return $pledge->assign($gift, '00000000-0000-4000-8000-0000000000c1', $amount, $credited, $available, self::ACTOR, new DateTimeImmutable('2026-02-02T08:00:00Z'));
    }

    private function message(callable $action): string
    {
        try {
            $action();
        } catch (GivingException $error) {
            return $error->getMessage();
        }
        self::fail('accepted');
    }

    public function testANewPledgeIsOpenAtVersionZero(): void
    {
        $pledge = $this->pledge();

        self::assertSame(Pledge::OPEN, $pledge->status);
        self::assertSame(0, $pledge->version);
        self::assertNull($pledge->cancellationReason);
    }

    public function testThePeriodIsAtMostTenYearsFrom1900(): void
    {
        self::assertSame('2036-01-09', $this->pledge('2026-01-01', '2036-01-09')->endsOn->iso(), '3660 days is allowed');
        self::assertSame('1900-01-01', $this->pledge('1900-01-01', '1900-01-01')->startsOn->iso());
        foreach ([['2026-01-01', '2036-01-10'], ['2026-02-01', '2026-01-31'], ['1899-12-31', '1900-01-01']] as [$starts, $ends]) {
            self::assertSame(self::PERIOD, $this->message(fn (): Pledge => $this->pledge($starts, $ends)), "$starts..$ends");
        }
        self::assertSame(self::PERIOD, $this->message(static fn (): Pledge => Pledge::create(self::PLEDGE, null, self::FUND, Money::of(1, 'ZAR'), CalendarDate::of(2026, 1, 1), CalendarDate::of(2026, 1, 1), self::ACTOR, new DateTimeImmutable())));
        self::assertSame(self::PERIOD, $this->message(static fn (): Pledge => Pledge::create(self::PLEDGE, self::DONOR, self::FUND, Money::of(1, 'ZAR'), null, CalendarDate::of(2026, 1, 1), self::ACTOR, new DateTimeImmutable())));
    }

    public function testPeriodCurrencyAndAvailableBudgetsAreIndependentInvariants(): void
    {
        $pledge = $this->pledge();
        $donor = 'Choose a gift from this donor, currency and commitment period.';
        $capacity = 'The assignment exceeds the available gift allocation or remaining pledge target.';

        self::assertSame($donor, $this->message(fn (): PledgeAssignment => $this->assign($pledge, $this->gift('USD'), 100, 0, 10000)));
        self::assertSame($donor, $this->message(fn (): PledgeAssignment => $this->assign($pledge, $this->gift(date: '2025-02-01'), 100, 0, 10000)));
        self::assertSame($donor, $this->message(fn (): PledgeAssignment => $this->assign($pledge, $this->gift(donor: null), 100, 0, 10000)));
        self::assertSame($capacity, $this->message(fn (): PledgeAssignment => $this->assign($pledge, $this->gift(), 5000, 6000, 10000)));
        self::assertSame($capacity, $this->message(fn (): PledgeAssignment => $this->assign($pledge, $this->gift(), 5000, 0, 4000)));
        self::assertSame('Enter a positive amount of at most 10,000,000,000.00.', $this->message(fn (): PledgeAssignment => $this->assign($pledge, $this->gift(), 0, 0, 10000)));
    }

    public function testAnAssignmentCarriesThePledgeIdentityAndStartsActive(): void
    {
        $assignment = $this->assign($this->pledge('2026-02-01', '2026-02-01'), $this->gift(), 4000, 6000, 4000);

        self::assertSame([self::PLEDGE, self::FUND, self::DONOR, 'ZAR', 4000, PledgeAssignment::ACTIVE, 0, self::ACTOR], [
            $assignment->pledgeId, $assignment->fundId, $assignment->donorId, $assignment->currency, $assignment->amountMinor, $assignment->status, $assignment->version, $assignment->createdBy,
        ]);
        self::assertNull($assignment->cancelledBy);
    }

    public function testCancellingPreservesTheCommitmentAndRequiresItsCurrentVersion(): void
    {
        $original = $this->pledge();
        $cancelled = $original->cancel(0, '  Donor withdrew commitment ');

        self::assertEquals($original->target, $cancelled->target);
        self::assertSame(Pledge::CANCELLED, $cancelled->status);
        self::assertSame(1, $cancelled->version);
        self::assertSame('Donor withdrew commitment', $cancelled->cancellationReason);
        self::assertSame('This record changed. Refresh before trying again.', $this->message(static fn (): Pledge => $original->cancel(1, 'Stale screen')));
        self::assertSame('This pledge is cancelled.', $this->message(static fn () => $cancelled->requireOpen()));
        self::assertSame('This pledge is cancelled.', $this->message(fn (): PledgeAssignment => $this->assign($cancelled, $this->gift(), 100, 0, 10000)));
        self::assertSame('Cancellation reason is required and must be at most 1000 characters.', $this->message(static fn (): Pledge => $original->cancel(0, ' ')));
    }

    public function testRevisingOnlyAdvancesTheVersion(): void
    {
        $cancelled = $this->pledge()->cancel(0, 'Withdrawn');
        $revised = $cancelled->revised();

        self::assertSame(2, $revised->version);
        self::assertSame(Pledge::CANCELLED, $revised->status);
        self::assertSame('Withdrawn', $revised->cancellationReason);
    }

    public function testAssignmentCancellationKeepsItsOwnActorTimestampAndOriginalEvidence(): void
    {
        $at = new DateTimeImmutable('2026-02-02T08:00:00Z');
        $assignment = $this->assign($this->pledge(), $this->gift(), 100, 0, 10000);
        $canceller = '88888888-8888-4888-8888-888888888888';
        $later = $at->modify('+60 seconds');
        $cancelled = $assignment->cancel(0, ' Wrong pledge ', $canceller, $later);

        self::assertSame(self::ACTOR, $cancelled->createdBy);
        self::assertEquals($at, $cancelled->createdAt);
        self::assertSame($canceller, $cancelled->cancelledBy);
        self::assertEquals($later, $cancelled->cancelledAt);
        self::assertSame('Wrong pledge', $cancelled->cancellationReason);
        self::assertSame(1, $cancelled->version);
        self::assertSame('This record changed. Refresh before trying again.', $this->message(static fn (): PledgeAssignment => $assignment->cancel(1, 'Stale', $canceller, $later)));
        self::assertSame('This pledge assignment has already been cancelled.', $this->message(static fn (): PledgeAssignment => $cancelled->cancel(1, 'Again', $canceller, $later)));
    }

    public function testCancelledAssignmentEvidenceNeedsItsReasonActorAndTime(): void
    {
        $at = new DateTimeImmutable('2026-02-02T08:00:00Z');
        self::assertSame('Cancellation evidence needs its actor and time.', $this->message(static fn (): PledgeAssignment => PledgeAssignment::reconstitute('a', 'p', 'g', self::FUND, self::DONOR, 'ZAR', 100, PledgeAssignment::CANCELLED, 1, 'Wrong', null, $at, self::ACTOR, $at)));
        self::assertSame('Invalid pledge assignment evidence.', $this->message(static fn (): PledgeAssignment => PledgeAssignment::reconstitute('a', 'p', 'g', self::FUND, self::DONOR, 'ZAR', 100, 'VOID', 0, null, null, null, self::ACTOR, $at)));
    }
}
