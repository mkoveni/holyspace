<?php

declare(strict_types=1);

namespace App\Tests\Giving\Application;

use App\Giving\Application\Command\CreateReconciliation\CreateReconciliationCommand;
use App\Giving\Application\Command\CreateReconciliation\CreateReconciliationHandler;
use App\Giving\Application\Command\ReviewReconciliation\ReviewReconciliationCommand;
use App\Giving\Application\Command\ReviewReconciliation\ReviewReconciliationHandler;
use App\Giving\Application\GivingAccess;
use App\Giving\Application\GivingHash;
use App\Giving\Application\Query\GetReconciliation\GetReconciliationHandler;
use App\Giving\Application\Query\GetReconciliation\GetReconciliationQuery;
use App\Giving\Application\Query\ListReconciliations\ListReconciliationsHandler;
use App\Giving\Application\Query\ListReconciliations\ListReconciliationsQuery;
use App\Giving\Domain\Allocation;
use App\Giving\Domain\Gift;
use App\Giving\Domain\GivingException;
use App\Giving\Domain\Money;
use App\Giving\Domain\Reversal;
use App\IdentityAccess\Domain\IdentityException;
use App\Shared\Application\ChurchCalendar;
use App\Shared\Domain\CalendarDate;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

/** Reconciliation handlers in isolation: no database, no transaction, no identity context. */
final class ReconciliationHandlersTest extends TestCase
{
    private const string ACTOR = '99999999-9999-4999-8999-999999999999';
    private const string FIRST = '00000000-0000-4000-8000-0000000000a1';
    private const string SECOND = '00000000-0000-4000-8000-0000000000a2';
    private const string BATCH = '00000000-0000-4000-8000-0000000000b1';
    private const string REVIEW = '00000000-0000-4000-8000-0000000000c1';
    private const string FUND = '00000000-0000-4000-8000-000000000001';
    private const string CONFLICT = 'Every selected gift must be unreconciled, unreversed, in the batch currency and received by the reconciliation date.';

    private LockLog $log;
    private InMemoryGifts $gifts;
    private InMemoryReconciliations $batches;
    private RecordingHistory $history;
    private RecordingPermissions $permissions;
    private MockClock $clock;

    protected function setUp(): void
    {
        $this->log = new LockLog();
        $this->gifts = new InMemoryGifts($this->log);
        $this->batches = new InMemoryReconciliations($this->log);
        $this->history = new RecordingHistory();
        $this->permissions = new RecordingPermissions(self::ACTOR, ['giving.reconcile', 'giving.read']);
        // Still 1 February in UTC, but already 00:30 on 2 February in Johannesburg: the church's date is the 2nd.
        $this->clock = new MockClock('2026-02-01 22:30:00 UTC');
        $this->seed(self::FIRST, 10000, '2026-01-05');
        $this->seed(self::SECOND, 2500, '2026-01-06');
    }

    private function seed(string $id, int $amount, string $receivedOn, string $currency = 'ZAR'): void
    {
        $this->gifts->gifts[$id] = Gift::reconstitute($id, null, Money::of($amount, $currency), CalendarDate::parse($receivedOn), 'BANK', null, [new Allocation(self::FUND, $amount)], self::ACTOR, new DateTimeImmutable($receivedOn.'T08:00:00Z'));
    }

    /** @param list<string|null>|null $gifts */
    private function create(?array $gifts = null, int $expected = 12500, ?string $reason = ' Verified bank evidence ', string $on = '2026-02-02', string $actor = self::ACTOR, string $currency = 'ZAR', string $requestId = self::BATCH): string
    {
        return (new CreateReconciliationHandler(
            new GivingAccess($this->permissions),
            $this->gifts,
            $this->batches,
            $this->history,
            new ChurchCalendar($this->clock, 'Africa/Johannesburg'),
            $this->clock,
        ))->handle(new CreateReconciliationCommand($actor, $requestId, ' BANK-001 ', $expected, $currency, CalendarDate::parse($on), $reason, $gifts ?? [self::SECOND, self::FIRST]));
    }

    private function review(int $expectedVersion = 0, ?string $outcome = 'EXPLAINED', string $requestId = self::REVIEW, string $actor = self::ACTOR): string
    {
        return (new ReviewReconciliationHandler(
            new GivingAccess($this->permissions),
            $this->gifts,
            $this->batches,
            $this->history,
            $this->clock,
        ))->handle(new ReviewReconciliationCommand($actor, $requestId, self::BATCH, $expectedVersion, $outcome, ' Bank correction verified '));
    }

    private function message(callable $action): string
    {
        try {
            $action();
        } catch (GivingException $error) {
            return $error->getMessage();
        }
        self::fail('the command was accepted');
    }

    private function reverse(string $gift): void
    {
        $this->gifts->reversals['r'.$gift] = Reversal::reconstitute('r'.$gift, $gift, CalendarDate::of(2026, 1, 20), 'Duplicate', self::ACTOR, new DateTimeImmutable());
    }

    public function testCreatingFreezesTheGiftsSortedByIdWithTheirSumAndAuditsIt(): void
    {
        self::assertSame(self::BATCH, $this->create());

        $batch = $this->batches->batches[self::BATCH];
        self::assertSame(['BANK-001', 12500, 12500, 'Verified bank evidence', [self::FIRST, self::SECOND], '2026-02-02'], [
            $batch->reference, $batch->expectedMinor, $batch->actualMinor, $batch->reason, $batch->giftIds, $batch->reconciledOn->iso(),
        ]);
        self::assertSame(
            GivingHash::of('batch', self::ACTOR, 'BANK-001', 12500, 'ZAR', CalendarDate::of(2026, 2, 2), 'Verified bank evidence', [self::FIRST, self::SECOND]),
            $this->batches->hashes[self::BATCH],
        );
        self::assertSame(
            GivingHash::of('batch', self::ACTOR, 'BANK-001', 12500, 'ZAR', CalendarDate::of(2026, 2, 2), 'Verified bank evidence', '['.self::FIRST.', '.self::SECOND.']'),
            $this->batches->hashes[self::BATCH],
            'the gift ids hash as Java List<UUID>.toString',
        );
        self::assertSame(['gift:'.self::FIRST, 'gift:'.self::SECOND], $this->log->locks);
        self::assertSame(['BATCH:BATCH_CREATED:'.self::BATCH.':Verified bank evidence'], $this->history->entries);
        self::assertEquals(new DateTimeImmutable('2026-02-01 22:30:00 UTC'), $batch->createdAt);
    }

    public function testAnIdenticalRetryReturnsTheBatchBeforeLockingAndADifferentOneConflicts(): void
    {
        $this->create();
        $this->log->locks = [];

        self::assertSame(self::BATCH, $this->create());
        self::assertSame([], $this->log->locks, 'a replay is answered before any gift is locked');
        self::assertSame('This request identifier was used for different details.', $this->message(fn () => $this->create(expected: 12000, reason: 'Short')));
        self::assertCount(1, $this->history->entries);
    }

    public function testADiscrepancyNeedsAnExplanation(): void
    {
        self::assertSame('Discrepancy explanation is required and must be at most 1000 characters.', $this->message(fn () => $this->create(expected: 13000, reason: null)));
        $this->create(expected: 13000, reason: 'Recorded bank charge pending review');
        self::assertSame([13000, 12500], [$this->batches->batches[self::BATCH]->expectedMinor, $this->batches->batches[self::BATCH]->actualMinor]);
    }

    public function testOnlyEligibleGiftsCanBeReconciled(): void
    {
        $this->seed('00000000-0000-4000-8000-0000000000a3', 100, '2026-01-07', 'USD');
        self::assertSame(self::CONFLICT, $this->message(fn () => $this->create(['00000000-0000-4000-8000-0000000000a3'], 100)), 'another currency');
        self::assertSame(self::CONFLICT, $this->message(fn () => $this->create([self::SECOND], 2500, on: '2026-01-05')), 'received after the reconciliation date');
        $this->reverse(self::SECOND);
        self::assertSame(self::CONFLICT, $this->message(fn () => $this->create([self::SECOND], 2500)), 'reversed');
        $this->create([self::FIRST], 10000);
        self::assertSame(self::CONFLICT, $this->message(fn () => $this->create([self::FIRST], 10000, requestId: '00000000-0000-4000-8000-0000000000b2')), 'already reconciled');
        self::assertCount(1, $this->batches->batches);
        self::assertCount(1, $this->history->entries);
    }

    public function testTheGiftListAndDatesAreCheckedBeforeAnyLock(): void
    {
        foreach ([[], [self::FIRST, self::FIRST], [self::FIRST, null], array_map(static fn (int $i): string => \sprintf('00000000-0000-4000-8000-%012d', $i), range(1, 101))] as $gifts) {
            self::assertSame('Select 1–100 distinct gifts.', $this->message(fn () => $this->create($gifts)));
        }
        self::assertSame('Choose a date from 1900 through today.', $this->message(fn () => $this->create(on: '2026-02-03')));
        self::assertSame([], $this->log->locks);
        self::assertSame('The Giving record was not found.', $this->message(fn () => $this->create([self::FIRST, '00000000-0000-4000-8000-0000000000ff'])));
    }

    public function testAReviewLocksTheGiftsSortedThenTheBatchAndRecordsTheReversedAmount(): void
    {
        $this->create();
        $this->log->locks = [];
        $this->batches->reversed[self::BATCH] = 2500;

        self::assertSame(self::REVIEW, $this->review());

        $review = $this->batches->reviews[self::REVIEW];
        self::assertSame([self::BATCH, 1, 'EXPLAINED', 'Bank correction verified', 2500], [$review->batchId, $review->version, $review->outcome, $review->note, $review->reversedMinor]);
        self::assertSame(['gift:'.self::FIRST, 'gift:'.self::SECOND, 'batch:'.self::BATCH], $this->log->locks);
        self::assertSame(GivingHash::of('batch-review', self::ACTOR, self::BATCH, 0, 'EXPLAINED', 'Bank correction verified'), $this->batches->reviewHashes[self::REVIEW]);
        self::assertSame('BATCH:BATCH_REVIEWED:'.self::BATCH.':Bank correction verified', $this->history->entries[1]);
    }

    public function testReviewRetriesAreIdempotentAndStaleVersionsAreRejected(): void
    {
        $this->create();
        $this->review();

        self::assertSame(self::REVIEW, $this->review(), 'a retry survives its own version bump');
        self::assertSame('This record changed. Refresh before trying again.', $this->message(fn () => $this->review(requestId: '00000000-0000-4000-8000-0000000000c2')));
        self::assertSame('00000000-0000-4000-8000-0000000000c2', $this->review(1, 'FOLLOW_UP', '00000000-0000-4000-8000-0000000000c2'));
        self::assertSame(2, $this->batches->reviews['00000000-0000-4000-8000-0000000000c2']->version);
        self::assertSame('Choose follow-up or explained.', $this->message(fn () => $this->review(2, 'DONE', '00000000-0000-4000-8000-0000000000c3')));
        self::assertCount(3, $this->history->entries, 'one creation and two reviews');
    }

    public function testAnUnknownBatchIsMissing(): void
    {
        self::assertSame('The Giving record was not found.', $this->message(fn () => $this->review()));
        self::assertSame([], $this->log->locks);
    }

    public function testThePermissionIsCheckedFirstAndASpoofedActorIsRefused(): void
    {
        $this->create();
        $this->log->locks = [];
        foreach ([[self::ACTOR, ['giving.read', 'giving.manage']], ['00000000-0000-4000-8000-000000000999', ['giving.reconcile']]] as [$actor, $granted]) {
            $this->permissions = new RecordingPermissions(self::ACTOR, $granted);
            foreach ([fn () => $this->create(actor: $actor, requestId: '00000000-0000-4000-8000-0000000000b9'), fn () => $this->review(actor: $actor)] as $command) {
                try {
                    $command();
                    self::fail('ran without giving.reconcile for the trusted caller');
                } catch (IdentityException) {
                }
            }
            self::assertSame([$actor.':giving.reconcile', $actor.':giving.reconcile'], $this->permissions->checks);
        }
        self::assertSame([], $this->log->locks);
        self::assertCount(1, $this->batches->batches);
        self::assertSame([], $this->batches->reviews);
        self::assertCount(1, $this->history->entries);
    }

    public function testReadsNeedGivingReadAndCheckThePage(): void
    {
        $reader = new FakeReconciliationReader();
        $access = new GivingAccess($this->permissions);

        self::assertSame(0, (new ListReconciliationsHandler($access, $reader))->handle(new ListReconciliationsQuery(25, 0))->total);
        self::assertSame('Use a page size of 1–100 and an offset of 0–100000.', $this->message(static fn () => (new ListReconciliationsHandler($access, $reader))->handle(new ListReconciliationsQuery(101, 0))));
        self::assertSame('The Giving record was not found.', $this->message(static fn () => (new GetReconciliationHandler($access, $reader))->handle(new GetReconciliationQuery(self::BATCH))));
        self::assertSame(['list:25:0', 'detail:'.self::BATCH], $reader->calls);

        $access = new GivingAccess(new RecordingPermissions(self::ACTOR, ['giving.reconcile']));
        foreach ([
            static fn () => (new ListReconciliationsHandler($access, $reader))->handle(new ListReconciliationsQuery(25, 0)),
            static fn () => (new GetReconciliationHandler($access, $reader))->handle(new GetReconciliationQuery(self::BATCH)),
        ] as $read) {
            try {
                $read();
                self::fail('read without giving.read');
            } catch (IdentityException) {
            }
        }
        self::assertSame(['list:25:0', 'detail:'.self::BATCH], $reader->calls, 'the reader is not reached without permission');
    }
}
