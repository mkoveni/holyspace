<?php

declare(strict_types=1);

namespace App\Tests\Giving\Application;

use App\Giving\Application\Command\AssignPledgeGift\AssignPledgeGiftCommand;
use App\Giving\Application\Command\AssignPledgeGift\AssignPledgeGiftHandler;
use App\Giving\Application\Command\CancelPledge\CancelPledgeCommand;
use App\Giving\Application\Command\CancelPledge\CancelPledgeHandler;
use App\Giving\Application\Command\CancelPledgeAssignment\CancelPledgeAssignmentCommand;
use App\Giving\Application\Command\CancelPledgeAssignment\CancelPledgeAssignmentHandler;
use App\Giving\Application\Command\CreatePledge\CreatePledgeCommand;
use App\Giving\Application\Command\CreatePledge\CreatePledgeHandler;
use App\Giving\Application\GivingAccess;
use App\Giving\Application\GivingHash;
use App\Giving\Application\Query\GetPledge\GetPledgeHandler;
use App\Giving\Application\Query\GetPledge\GetPledgeQuery;
use App\Giving\Application\Query\ListPledgeGiftCandidates\ListPledgeGiftCandidatesHandler;
use App\Giving\Application\Query\ListPledgeGiftCandidates\ListPledgeGiftCandidatesQuery;
use App\Giving\Application\Query\ListPledges\ListPledgesHandler;
use App\Giving\Application\Query\ListPledges\ListPledgesQuery;
use App\Giving\Domain\Allocation;
use App\Giving\Domain\Fund;
use App\Giving\Domain\Gift;
use App\Giving\Domain\GivingException;
use App\Giving\Domain\Money;
use App\Giving\Domain\Pledge;
use App\Giving\Domain\PledgeAssignment;
use App\Giving\Domain\Reversal;
use App\IdentityAccess\Domain\IdentityException;
use App\Shared\Domain\CalendarDate;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

/** Pledge handlers in isolation: no database, no transaction, no identity context. */
final class PledgeHandlersTest extends TestCase
{
    private const string ACTOR = '99999999-9999-4999-8999-999999999999';
    private const string PLEDGE = '00000000-0000-4000-8000-0000000000a1';
    private const string ASSIGNMENT = '00000000-0000-4000-8000-0000000000c1';
    private const string GIFT = '00000000-0000-4000-8000-0000000000aa';
    private const string FUND = '00000000-0000-4000-8000-000000000001';
    private const string OTHER = '00000000-0000-4000-8000-000000000002';
    private const string DONOR = '00000000-0000-4000-8000-0000000000dd';

    private LockLog $log;
    private InMemoryFunds $funds;
    private InMemoryGifts $gifts;
    private InMemoryPledges $pledges;
    private FakePeople $people;
    private RecordingHistory $history;
    private RecordingPermissions $permissions;
    private MockClock $clock;

    protected function setUp(): void
    {
        $this->log = new LockLog();
        $this->funds = new InMemoryFunds($this->log);
        $this->gifts = new InMemoryGifts($this->log);
        $this->pledges = new InMemoryPledges($this->log);
        $this->people = new FakePeople($this->log);
        $this->history = new RecordingHistory();
        $this->permissions = new RecordingPermissions(self::ACTOR, ['giving.manage', 'giving.read']);
        $this->clock = new MockClock('2026-03-01 08:00:00 UTC');
        $this->funds->funds[self::FUND] = Fund::create(self::FUND, 'General', '');
        $this->people->add(self::DONOR);
    }

    private function access(): GivingAccess
    {
        return new GivingAccess($this->permissions);
    }

    private function create(int $amount = 10000, string $actor = self::ACTOR, string $fund = self::FUND, string $ends = '2026-12-31'): string
    {
        return (new CreatePledgeHandler($this->access(), $this->pledges, $this->funds, $this->history, $this->people, $this->clock))
            ->handle(new CreatePledgeCommand($actor, self::PLEDGE, self::DONOR, $fund, $amount, 'ZAR', CalendarDate::of(2026, 1, 1), CalendarDate::parse($ends)));
    }

    private function seedPledge(int $target = 10000): void
    {
        $this->pledges->pledges[self::PLEDGE] = Pledge::create(self::PLEDGE, self::DONOR, self::FUND, Money::of($target, 'ZAR'), CalendarDate::of(2026, 1, 1), CalendarDate::of(2026, 12, 31), self::ACTOR, new DateTimeImmutable('2026-01-01T08:00:00Z'));
        $this->pledges->hashes[self::PLEDGE] = 'seeded';
    }

    /** @param list<Allocation> $allocations */
    private function seedGift(array $allocations = [], string $id = self::GIFT, ?string $donor = self::DONOR): void
    {
        $allocations = [] === $allocations ? [new Allocation(self::FUND, 10000)] : $allocations;
        $total = array_sum(array_map(static fn (Allocation $a): int => $a->amountMinor, $allocations));
        $this->gifts->gifts[$id] = Gift::reconstitute($id, $donor, Money::of($total, 'ZAR'), CalendarDate::of(2026, 1, 5), 'BANK', null, $allocations, self::ACTOR, new DateTimeImmutable('2026-01-05T08:00:00Z'));
    }

    private function assign(int $amount = 10000, int $version = 0, string $request = self::ASSIGNMENT, string $gift = self::GIFT, string $actor = self::ACTOR): string
    {
        return (new AssignPledgeGiftHandler($this->access(), $this->gifts, $this->pledges, $this->history, $this->clock))
            ->handle(new AssignPledgeGiftCommand($actor, $request, self::PLEDGE, $gift, $version, $amount));
    }

    private function cancelAssignment(int $version = 0, ?string $reason = 'Wrong pledge', string $actor = self::ACTOR): void
    {
        (new CancelPledgeAssignmentHandler($this->access(), $this->gifts, $this->pledges, $this->history, $this->clock))
            ->handle(new CancelPledgeAssignmentCommand($actor, self::ASSIGNMENT, $version, $reason));
    }

    private function cancel(int $version = 0, ?string $reason = 'Withdrawn', string $actor = self::ACTOR): void
    {
        (new CancelPledgeHandler($this->access(), $this->pledges, $this->history, $this->clock))
            ->handle(new CancelPledgeCommand($actor, self::PLEDGE, $version, $reason));
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

    public function testCreatingStoresTheHashLocksDonorThenFundAndAudits(): void
    {
        self::assertSame(self::PLEDGE, $this->create());

        self::assertSame(
            GivingHash::of('pledge', self::ACTOR, self::DONOR, self::FUND, Money::of(10000, 'ZAR'), CalendarDate::of(2026, 1, 1), CalendarDate::of(2026, 12, 31)),
            $this->pledges->hashes[self::PLEDGE],
        );
        self::assertSame(['person:'.self::DONOR, 'fund:'.self::FUND], $this->log->locks);
        self::assertSame(['PLEDGE:PLEDGE_CREATED:'.self::PLEDGE.':'], $this->history->entries);
        self::assertEquals(new DateTimeImmutable('2026-03-01 08:00:00 UTC'), $this->pledges->pledges[self::PLEDGE]->createdAt);
    }

    public function testAnIdenticalRetryReturnsThePledgeAndDifferentDetailsConflict(): void
    {
        $this->create();
        $this->log->locks = [];

        self::assertSame(self::PLEDGE, $this->create());
        self::assertSame([], $this->log->locks);
        self::assertCount(1, $this->history->entries);
        self::assertSame('This request identifier was used for different details.', $this->message(fn (): string => $this->create(20000)));
    }

    public function testCreatingNeedsAnAvailableDonorAnActiveFundAndAValidPeriod(): void
    {
        $this->funds->funds[self::OTHER] = Fund::reconstitute(self::OTHER, 'Missions', '', false, 1);
        self::assertSame('Choose an active fund.', $this->message(fn (): string => $this->create(fund: self::OTHER)));
        self::assertSame('Choose an active fund.', $this->message(fn (): string => $this->create(fund: '00000000-0000-4000-8000-00000000ffff')));
        self::assertSame('A pledge needs a donor, fund, currency and a commitment period of at most ten years.', $this->message(fn (): string => $this->create(ends: '2025-12-31')));
        self::assertSame('Enter a positive amount of at most 10,000,000,000.00.', $this->message(fn (): string => $this->create(0)));
        $this->people->add(self::DONOR, available: false);
        self::assertSame('Choose an available donor.', $this->message(fn (): string => $this->create()));
        self::assertSame([], $this->pledges->pledges);
        self::assertSame([], $this->history->entries);
    }

    public function testCreatingChecksThePermissionFirstAndRefusesASpoofedActor(): void
    {
        foreach ([new RecordingPermissions(self::ACTOR, ['giving.read']), new RecordingPermissions('someone-else', ['giving.manage'])] as $permissions) {
            $this->permissions = $permissions;
            try {
                $this->create(0);
                self::fail('created without permission');
            } catch (IdentityException) {
                self::assertSame([self::ACTOR.':giving.manage'], $permissions->checks);
            }
        }
        self::assertSame([], $this->log->locks);
    }

    public function testAssigningLocksTheGiftThenThePledgeAndAdvancesThePledge(): void
    {
        $this->seedPledge();
        $this->seedGift();

        self::assertSame(self::ASSIGNMENT, $this->assign(6000));

        self::assertSame(['gift:'.self::GIFT, 'pledge:'.self::PLEDGE], $this->log->locks);
        self::assertSame(GivingHash::of('pledge-assignment', self::ACTOR, self::PLEDGE, self::GIFT, 0, 6000), $this->pledges->assignmentHashes[self::ASSIGNMENT]);
        self::assertSame(1, $this->pledges->pledges[self::PLEDGE]->version);
        self::assertSame(6000, $this->pledges->assignments[self::ASSIGNMENT]->amountMinor);
        self::assertSame(['PLEDGE:GIFT_ASSIGNED:'.self::PLEDGE.':'], $this->history->entries);
    }

    public function testAnAssignmentRetryIsIdempotentAfterThePledgeVersionChanged(): void
    {
        $this->seedPledge();
        $this->seedGift();
        $this->assign();

        self::assertSame(self::ASSIGNMENT, $this->assign());
        self::assertCount(1, $this->pledges->assignments);
        self::assertSame(1, $this->pledges->pledges[self::PLEDGE]->version);
        self::assertSame('This request identifier was used for different details.', $this->message(fn (): string => $this->assign(5000)));
    }

    public function testTheAvailableAllocationExcludesOtherFundsRefundsAndActiveAssignments(): void
    {
        $this->seedPledge(20000);
        $this->seedGift([new Allocation(self::FUND, 7000), new Allocation(self::OTHER, 3000)]);
        $this->gifts->refunds[self::GIFT] = 1000;
        $capacity = 'The assignment exceeds the available gift allocation or remaining pledge target.';

        self::assertSame($capacity, $this->message(fn (): string => $this->assign(6001)));
        $this->assign(4000);
        self::assertSame($capacity, $this->message(fn (): string => $this->assign(2001, 1, '00000000-0000-4000-8000-0000000000c2')));
        self::assertSame('00000000-0000-4000-8000-0000000000c2', $this->assign(2000, 1, '00000000-0000-4000-8000-0000000000c2'));
    }

    public function testTheRemainingTargetBoundsTheAssignment(): void
    {
        $this->seedPledge(5000);
        $this->seedGift();

        self::assertSame('The assignment exceeds the available gift allocation or remaining pledge target.', $this->message(fn (): string => $this->assign(5001)));
        self::assertSame(self::ASSIGNMENT, $this->assign(5000));
    }

    public function testAssigningChecksVersionReversalAndDonorInJavasOrder(): void
    {
        $this->seedPledge();
        $this->seedGift();
        self::assertSame('This record changed. Refresh before trying again.', $this->message(fn (): string => $this->assign(version: 1)));
        $this->gifts->reversals['r'] = Reversal::record('r', self::GIFT, CalendarDate::of(2026, 2, 1), 'Duplicate', self::ACTOR, new DateTimeImmutable());
        self::assertSame('A reversed gift cannot fulfil a pledge.', $this->message(fn (): string => $this->assign()));
        $this->seedGift(id: self::GIFT, donor: null);
        $this->gifts->reversals = [];
        self::assertSame('Choose a gift from this donor, currency and commitment period.', $this->message(fn (): string => $this->assign()));
        self::assertSame('The Giving record was not found.', $this->message(fn (): string => $this->assign(gift: '00000000-0000-4000-8000-00000000ffff')));
        self::assertSame([], $this->pledges->assignments);
        self::assertSame([], $this->history->entries);
    }

    public function testACancelledPledgeAcceptsNoAssignment(): void
    {
        $this->seedPledge();
        $this->seedGift();
        $this->cancel();

        self::assertSame('This pledge is cancelled.', $this->message(fn (): string => $this->assign(version: 1)));
    }

    public function testAssigningChecksThePermissionFirstAndRefusesASpoofedActor(): void
    {
        $this->seedPledge();
        $this->seedGift();
        $this->permissions = new RecordingPermissions('someone-else', ['giving.manage']);
        try {
            $this->assign();
            self::fail('assigned for a spoofed actor');
        } catch (IdentityException) {
            self::assertSame([self::ACTOR.':giving.manage'], $this->permissions->checks);
        }
        self::assertSame([], $this->log->locks);
    }

    public function testCancellingAnAssignmentReleasesItKeepsTheEvidenceAndAdvancesThePledge(): void
    {
        $this->seedPledge();
        $this->seedGift();
        $this->assign();
        $this->log->locks = [];
        $this->pledges->assignmentReads = 0;
        $this->clock->modify('+1 hour');

        $this->cancelAssignment(reason: ' Wrong pledge ');

        $cancelled = $this->pledges->assignments[self::ASSIGNMENT];
        self::assertSame([PledgeAssignment::CANCELLED, 1, 'Wrong pledge', self::ACTOR], [$cancelled->status, $cancelled->version, $cancelled->cancellationReason, $cancelled->cancelledBy]);
        self::assertEquals(new DateTimeImmutable('2026-03-01 09:00:00 UTC'), $cancelled->cancelledAt);
        self::assertSame(['gift:'.self::GIFT, 'pledge:'.self::PLEDGE], $this->log->locks);
        self::assertSame(2, $this->pledges->assignmentReads, 'the assignment is read again under the gift and pledge locks');
        self::assertSame(2, $this->pledges->pledges[self::PLEDGE]->version);
        self::assertSame('PLEDGE:ASSIGNMENT_CANCELLED:'.self::PLEDGE.':Wrong pledge', $this->history->entries[1]);
        self::assertSame(0, $this->pledges->credited(self::PLEDGE));
    }

    public function testCancellingAnAssignmentChecksVersionStatusAndExistence(): void
    {
        self::assertSame('The Giving record was not found.', $this->message(fn () => $this->cancelAssignment()));
        $this->seedPledge();
        $this->seedGift();
        $this->assign();
        self::assertSame('This record changed. Refresh before trying again.', $this->message(fn () => $this->cancelAssignment(1)));
        $this->cancelAssignment();
        self::assertSame('This pledge assignment has already been cancelled.', $this->message(fn () => $this->cancelAssignment(1)));
        self::assertCount(2, $this->history->entries);
    }

    public function testCancellingAPledgeKeepsItsTargetAndAuditsTheReason(): void
    {
        $this->seedPledge();

        $this->cancel(reason: ' Donor withdrew ');

        $pledge = $this->pledges->pledges[self::PLEDGE];
        self::assertSame([Pledge::CANCELLED, 1, 'Donor withdrew', 10000], [$pledge->status, $pledge->version, $pledge->cancellationReason, $pledge->target->amountMinor]);
        self::assertSame(['pledge:'.self::PLEDGE], $this->log->locks);
        self::assertSame(['PLEDGE:PLEDGE_CANCELLED:'.self::PLEDGE.':Donor withdrew'], $this->history->entries);
        self::assertSame('This record changed. Refresh before trying again.', $this->message(fn () => $this->cancel()));
        self::assertSame('This pledge is cancelled.', $this->message(fn () => $this->cancel(1)));
        self::assertCount(1, $this->history->entries);
    }

    public function testCancellingChecksThePermissionFirst(): void
    {
        $this->seedPledge();
        $this->permissions = new RecordingPermissions(self::ACTOR, ['giving.read']);
        foreach ([fn () => $this->cancel(), fn () => $this->cancelAssignment()] as $action) {
            try {
                $action();
                self::fail('cancelled without giving.manage');
            } catch (IdentityException) {
            }
        }
        self::assertSame([self::ACTOR.':giving.manage', self::ACTOR.':giving.manage'], $this->permissions->checks);
        self::assertSame([], $this->log->locks);
        self::assertSame(Pledge::OPEN, $this->pledges->pledges[self::PLEDGE]->status);
    }

    public function testReadsCheckGivingReadPageAndStatusBeforeTheReader(): void
    {
        $reader = new FakePledgeReader();
        $list = new ListPledgesHandler($this->access(), $reader);
        self::assertSame(0, $list->handle(new ListPledgesQuery(self::DONOR, 'OPEN', 25, 0))->total);
        self::assertSame('Choose open or cancelled pledges.', $this->message(static fn () => $list->handle(new ListPledgesQuery(null, 'open', 25, 0))));
        self::assertSame('Use a page size of 1–100 and an offset of 0–100000.', $this->message(static fn () => $list->handle(new ListPledgesQuery(null, null, 101, 0))));
        self::assertSame('The Giving record was not found.', $this->message(fn () => (new GetPledgeHandler($this->access(), $reader))->handle(new GetPledgeQuery(self::PLEDGE, 25, 0))));
        self::assertSame('The Giving record was not found.', $this->message(fn () => (new ListPledgeGiftCandidatesHandler($this->access(), $reader))->handle(new ListPledgeGiftCandidatesQuery(self::PLEDGE, 25, 0))));
        self::assertSame('Use a page size of 1–100 and an offset of 0–100000.', $this->message(fn () => (new ListPledgeGiftCandidatesHandler($this->access(), $reader))->handle(new ListPledgeGiftCandidatesQuery(self::PLEDGE, 0, 0))));
        self::assertSame(['list:'.self::DONOR.':OPEN:25:0', 'detail:'.self::PLEDGE.':25:0', 'candidates:'.self::PLEDGE.':25:0'], $reader->calls);

        $denied = new RecordingPermissions(self::ACTOR, ['giving.manage']);
        $this->expectException(IdentityException::class);
        try {
            (new ListPledgesHandler(new GivingAccess($denied), $reader))->handle(new ListPledgesQuery(null, 'nope', 0, 0));
        } finally {
            self::assertSame(['giving.read'], $denied->checks);
        }
    }
}
