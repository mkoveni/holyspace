<?php

declare(strict_types=1);

namespace App\Tests\Giving\Application;

use App\Giving\Application\Command\RecordGift\RecordGiftCommand;
use App\Giving\Application\Command\RecordGift\RecordGiftHandler;
use App\Giving\Application\Command\ReverseGift\ReverseGiftCommand;
use App\Giving\Application\Command\ReverseGift\ReverseGiftHandler;
use App\Giving\Application\Contract\GiftFilter;
use App\Giving\Application\GivingAccess;
use App\Giving\Application\GivingHash;
use App\Giving\Application\Query\GetGift\GetGiftHandler;
use App\Giving\Application\Query\GetGift\GetGiftQuery;
use App\Giving\Application\Query\ListGifts\ListGiftsHandler;
use App\Giving\Application\Query\ListGifts\ListGiftsQuery;
use App\Giving\Domain\Allocation;
use App\Giving\Domain\Fund;
use App\Giving\Domain\Gift;
use App\Giving\Domain\GivingException;
use App\Giving\Domain\Money;
use App\IdentityAccess\Domain\IdentityException;
use App\Shared\Application\ChurchCalendar;
use App\Shared\Domain\CalendarDate;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

/** Gift handlers in isolation: no database, no transaction, no identity context. */
final class GiftHandlersTest extends TestCase
{
    private const string ACTOR = '99999999-9999-4999-8999-999999999999';
    private const string GIFT = '00000000-0000-4000-8000-0000000000aa';
    private const string REVERSAL = '00000000-0000-4000-8000-0000000000bb';
    private const string FUND = '00000000-0000-4000-8000-000000000001';
    private const string OTHER = '00000000-0000-4000-8000-000000000002';
    private const string DONOR = '00000000-0000-4000-8000-0000000000dd';

    private LockLog $log;
    private InMemoryFunds $funds;
    private InMemoryGifts $gifts;
    private FakePeople $people;
    private RecordingHistory $history;
    private RecordingPermissions $permissions;
    private MockClock $clock;

    protected function setUp(): void
    {
        $this->log = new LockLog();
        $this->funds = new InMemoryFunds($this->log);
        $this->gifts = new InMemoryGifts($this->log);
        $this->people = new FakePeople($this->log);
        $this->history = new RecordingHistory();
        $this->permissions = new RecordingPermissions(self::ACTOR, ['giving.manage', 'giving.read', 'giving.reverse']);
        // Still 1 June in UTC, but already 00:30 on 2 June in Johannesburg: the church's date is the 2nd.
        $this->clock = new MockClock('2026-06-01 22:30:00 UTC');
        $this->funds->funds[self::FUND] = Fund::create(self::FUND, 'General', '');
        $this->funds->funds[self::OTHER] = Fund::create(self::OTHER, 'Missions', '');
        $this->people->add(self::DONOR);
    }

    /** @param list<Allocation|null>|null $allocations */
    private function record(
        ?array $allocations = null,
        ?string $donor = null,
        ?string $method = 'BANK',
        string $receivedOn = '2026-06-01',
        string $actor = self::ACTOR,
        ?string $reference = null,
    ): string {
        return (new RecordGiftHandler(
            new GivingAccess($this->permissions),
            $this->gifts,
            $this->funds,
            $this->history,
            $this->people,
            new ChurchCalendar($this->clock, 'Africa/Johannesburg'),
            $this->clock,
        ))->handle(new RecordGiftCommand(
            $actor,
            self::GIFT,
            $donor,
            10000,
            'ZAR',
            CalendarDate::parse($receivedOn),
            $method,
            $reference,
            $allocations ?? [new Allocation(self::OTHER, 3000), new Allocation(self::FUND, 7000)],
        ));
    }

    private function reverse(string $on = '2026-06-02', ?string $reason = 'Duplicate bank entry', string $actor = self::ACTOR, string $requestId = self::REVERSAL): string
    {
        return (new ReverseGiftHandler(
            new GivingAccess($this->permissions),
            $this->gifts,
            $this->history,
            new ChurchCalendar($this->clock, 'Africa/Johannesburg'),
            $this->clock,
        ))->handle(new ReverseGiftCommand($actor, $requestId, self::GIFT, CalendarDate::parse($on), $reason));
    }

    private function seedGift(string $method = 'BANK'): void
    {
        $this->gifts->gifts[self::GIFT] = Gift::reconstitute(self::GIFT, null, Money::of(10000, 'ZAR'), CalendarDate::of(2026, 5, 1), $method, null, [new Allocation(self::FUND, 10000)], self::ACTOR, new DateTimeImmutable('2026-05-01T08:00:00Z'));
        $this->gifts->hashes[self::GIFT] = 'seeded';
    }

    public function testPostingStoresTheGiftWithItsHashAndAuditsIt(): void
    {
        self::assertSame(self::GIFT, $this->record(donor: self::DONOR, reference: ' BANK-1 '));

        $sorted = [new Allocation(self::FUND, 7000), new Allocation(self::OTHER, 3000)];
        self::assertSame(
            GivingHash::of('gift', self::ACTOR, self::DONOR, Money::of(10000, 'ZAR'), CalendarDate::of(2026, 6, 1), 'BANK', 'BANK-1', $sorted),
            $this->gifts->hashes[self::GIFT],
        );
        self::assertSame(['GIFT:GIFT_POSTED:'.self::GIFT.':'], $this->history->entries);
        self::assertEquals(new DateTimeImmutable('2026-06-01 22:30:00 UTC'), $this->gifts->gifts[self::GIFT]->createdAt);
    }

    public function testTheRequestHashMatchesJavasListRendering(): void
    {
        $allocations = [new Allocation(self::FUND, 7000), new Allocation(self::OTHER, 3000)];
        $rendered = '[Allocation[fundId='.self::FUND.', amountMinor=7000], Allocation[fundId='.self::OTHER.', amountMinor=3000]]';

        self::assertSame(GivingHash::of('gift', $rendered), GivingHash::of('gift', $allocations));
    }

    public function testLocksTheDonorThenTheFundsSortedById(): void
    {
        $this->record(donor: self::DONOR);

        self::assertSame(['person:'.self::DONOR, 'fund:'.self::FUND, 'fund:'.self::OTHER], $this->log->locks);
    }

    public function testAnIdenticalRetryReturnsTheSameGiftWithoutLockingOrAuditingAgain(): void
    {
        $this->record();
        $this->log->locks = [];

        self::assertSame(self::GIFT, $this->record(allocations: [new Allocation(self::FUND, 7000), new Allocation(self::OTHER, 3000)]));
        self::assertSame([], $this->log->locks);
        self::assertCount(1, $this->history->entries);
    }

    public function testReusingARequestIdForDifferentDetailsIsAConflict(): void
    {
        $this->record();

        $this->expectException(GivingException::class);
        $this->expectExceptionMessage('This request identifier was already used for different details.');

        $this->record(method: 'CASH');
    }

    public function testOnlyManualMethodsArePosted(): void
    {
        foreach (['ONLINE', null, 'CARD'] as $method) {
            try {
                $this->record(method: $method);
                self::fail('posted a '.var_export($method, true).' gift');
            } catch (GivingException $error) {
                self::assertSame('Online gifts require verified Payments confirmation.', $error->getMessage());
            }
        }
        self::assertSame([], $this->gifts->gifts);
    }

    public function testTheReceivedDateIsNoLaterThanTheChurchsToday(): void
    {
        self::assertSame(self::GIFT, $this->record(receivedOn: '2026-06-02'));

        $this->expectException(GivingException::class);
        $this->expectExceptionMessage('Choose a date from 1900 through today.');

        $this->gifts->gifts = [];
        $this->gifts->hashes = [];
        $this->record(receivedOn: '2026-06-03');
    }

    public function testAnUnavailableOrUnknownDonorIsRejected(): void
    {
        $this->people->add(self::DONOR, available: false);
        foreach ([self::DONOR, '00000000-0000-4000-8000-0000000000ee'] as $donor) {
            try {
                $this->record(donor: $donor);
                self::fail('accepted donor '.$donor);
            } catch (GivingException $error) {
                self::assertSame('Choose an available person or record an anonymous gift.', $error->getMessage());
            }
        }
        self::assertSame([], $this->history->entries);
    }

    public function testAnArchivedOrMissingFundIsAConflict(): void
    {
        $this->funds->funds[self::OTHER] = Fund::reconstitute(self::OTHER, 'Missions', '', false, 1);

        $this->expectException(GivingException::class);
        $this->expectExceptionMessage('An allocated fund is missing or inactive.');

        try {
            $this->record();
        } finally {
            self::assertSame([], $this->gifts->gifts);
            self::assertSame([], $this->history->entries);
        }
    }

    public function testPostingChecksThePermissionFirstAndRefusesASpoofedActor(): void
    {
        foreach ([new RecordingPermissions(self::ACTOR, ['giving.read', 'giving.reverse']), new RecordingPermissions('someone-else', ['giving.manage'])] as $permissions) {
            $this->permissions = $permissions;
            try {
                $this->record(method: 'ONLINE');
                self::fail('posted without permission');
            } catch (IdentityException) {
                self::assertSame([self::ACTOR.':giving.manage'], $permissions->checks);
            }
        }
        self::assertSame([], $this->log->locks);
    }

    public function testAReversalIsStoredWithItsHashAndAudited(): void
    {
        $this->seedGift();

        self::assertSame(self::REVERSAL, $this->reverse(reason: ' Duplicate bank entry '));

        self::assertSame(
            GivingHash::of('reverse', self::ACTOR, self::GIFT, CalendarDate::of(2026, 6, 2), 'Duplicate bank entry'),
            $this->gifts->reversalHashes[self::REVERSAL],
        );
        self::assertSame(['gift:'.self::GIFT], $this->log->locks);
        self::assertSame(['GIFT:GIFT_REVERSED:'.self::GIFT.':Duplicate bank entry'], $this->history->entries);
    }

    public function testAnIdenticalReversalRetryReturnsTheSameReversal(): void
    {
        $this->seedGift();
        $this->reverse();

        self::assertSame(self::REVERSAL, $this->reverse());
        self::assertCount(1, $this->gifts->reversals);
        self::assertCount(1, $this->history->entries);
    }

    public function testASecondFullReversalIsAConflict(): void
    {
        $this->seedGift();
        $this->reverse();

        $this->expectException(GivingException::class);
        $this->expectExceptionMessage('This gift already has a full reversal.');

        $this->reverse(reason: 'Again', requestId: '00000000-0000-4000-8000-0000000000cc');
    }

    public function testOnlineGiftsAreCorrectedThroughPayments(): void
    {
        $this->seedGift('ONLINE');

        $this->expectException(GivingException::class);
        $this->expectExceptionMessage('Use Online payments to record a provider-completed refund.');

        $this->reverse();
    }

    public function testReversalDatesAreBoundedByTheGiftAndToday(): void
    {
        $this->seedGift();
        foreach (['2026-04-30' => "A reversal cannot precede the gift's received date.", '2026-06-03' => 'Choose a date from 1900 through today.'] as $on => $message) {
            try {
                $this->reverse(on: $on);
                self::fail('accepted '.$on);
            } catch (GivingException $error) {
                self::assertSame($message, $error->getMessage());
            }
        }
        self::assertSame([], $this->gifts->reversals);
    }

    public function testReversingAnUnknownGiftIsMissing(): void
    {
        $this->expectException(GivingException::class);
        $this->expectExceptionMessage('The Giving record was not found.');

        $this->reverse();
    }

    public function testReversingChecksItsOwnPermissionFirstAndValidatesBeforeLocking(): void
    {
        $this->seedGift();
        $this->permissions = new RecordingPermissions(self::ACTOR, ['giving.manage', 'giving.read']);
        try {
            $this->reverse();
            self::fail('reversed without giving.reverse');
        } catch (IdentityException) {
            self::assertSame([self::ACTOR.':giving.reverse'], $this->permissions->checks);
        }
        $this->permissions = new RecordingPermissions(self::ACTOR, ['giving.reverse']);
        try {
            $this->reverse(reason: ' ');
            self::fail('accepted a blank reason');
        } catch (GivingException) {
            self::assertSame([], $this->log->locks);
        }
    }

    public function testReadsCheckGivingReadAndValidateTheFilter(): void
    {
        $reader = new FakeGivingReader();
        $list = new ListGiftsHandler(new GivingAccess($this->permissions), $reader);
        $filter = new GiftFilter(CalendarDate::of(2026, 1, 1), CalendarDate::of(2026, 1, 31), null, null, null, 25, 0);
        self::assertSame(0, $list->handle(new ListGiftsQuery($filter))->total);
        self::assertSame([$filter], $reader->giftCalls);

        try {
            $list->handle(new ListGiftsQuery(new GiftFilter(CalendarDate::of(2026, 2, 1), CalendarDate::of(2026, 1, 1), null, null, null, 25, 0)));
            self::fail('accepted a reversed period');
        } catch (GivingException $error) {
            self::assertSame('Choose a period of at most ten years, with the end on or after the start.', $error->getMessage());
        }

        try {
            (new GetGiftHandler(new GivingAccess($this->permissions), $reader))->handle(new GetGiftQuery(self::GIFT));
            self::fail('found a missing gift');
        } catch (GivingException $error) {
            self::assertSame('The Giving record was not found.', $error->getMessage());
        }

        $denied = new RecordingPermissions(self::ACTOR, ['giving.manage', 'giving.reverse']);
        $this->expectException(IdentityException::class);
        try {
            (new GetGiftHandler(new GivingAccess($denied), $reader))->handle(new GetGiftQuery(self::GIFT));
        } finally {
            self::assertSame(['giving.read'], $denied->checks);
            self::assertSame([$filter, self::GIFT], $reader->giftCalls);
        }
    }
}
