<?php

declare(strict_types=1);

namespace App\Tests\Giving\Infrastructure;

use App\Giving\Domain\Allocation;
use App\Giving\Domain\Gift;
use App\Giving\Domain\Money;
use App\Giving\Domain\Pledge;
use App\Giving\Infrastructure\Persistence\DbalGiftStore;
use App\Giving\Infrastructure\Persistence\DbalPledgeStore;
use App\Shared\Domain\CalendarDate;
use App\Tests\Giving\Application\FakePeople;
use App\Tests\Support\DatabaseTestCase;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Symfony\Component\Clock\MockClock;

/**
 * Java's concurrency cases with two real database sessions. The first session runs a use case and keeps its transaction
 * open; a second, forked session runs a competing one and must block on the first session's lock (proved through
 * `pg_stat_activity`), then decide against the committed result once the first commits. This exercises the re-check each
 * handler makes after taking its locks, which a sequential retry would skip.
 */
final class GivingConcurrencyTest extends DatabaseTestCase
{
    private const string ACTOR = '99999999-9999-4999-8999-999999999999';
    private const string FUND = '00000000-0000-4000-8000-000000000001';
    private const string GIFT = '00000000-0000-4000-8000-0000000000aa';
    private const string DONOR = '00000000-0000-4000-8000-0000000000dd';
    private const string FIRST = '00000000-0000-4000-8000-000000000101';
    private const string SECOND = '00000000-0000-4000-8000-000000000102';

    private FakePeople $people;
    private MockClock $clock;

    protected function setUp(): void
    {
        parent::setUp();
        $this->people = new FakePeople();
        $this->people->add(self::DONOR);
        $this->clock = new MockClock(new DateTimeImmutable('2026-03-01T08:00:00Z'));
        $this->connection->insert('giving_funds', ['id' => self::FUND, 'name' => 'General', 'description' => '', 'request_hash' => str_repeat('a', 64)]);
    }

    private function session(Connection $connection): GivingSession
    {
        return new GivingSession($connection, self::ACTOR, $this->people, $this->clock);
    }

    private function seedGift(): void
    {
        $this->connection->transactional(function (): void {
            new DbalGiftStore($this->connection)->insert(
                Gift::record(self::GIFT, self::DONOR, Money::of(10000, 'ZAR'), CalendarDate::of(2026, 1, 5), 'BANK', null, [new Allocation(self::FUND, 10000)], self::ACTOR, new DateTimeImmutable('2026-01-05T08:00:00Z')),
                str_repeat('b', 64),
            );
        });
    }

    private function seedPledge(string $id): void
    {
        new DbalPledgeStore($this->connection)->insert(
            Pledge::create($id, self::DONOR, self::FUND, Money::of(10000, 'ZAR'), CalendarDate::of(2026, 1, 1), CalendarDate::of(2026, 12, 31), self::ACTOR, new DateTimeImmutable('2026-01-01T08:00:00Z')),
            str_repeat('c', 64),
        );
    }

    /**
     * Runs `$first` in this session with its transaction left open, starts `$second` in another session, proves the second
     * waits for the first session's lock, then commits and returns the second session's outcome.
     *
     * @param callable(GivingSession): string $first
     * @param callable(GivingSession): string $second
     *
     * @return array{string, string}
     */
    private function contend(callable $first, callable $second): array
    {
        $this->connection->beginTransaction();
        $won = $first($this->session($this->connection));
        $competitor = ParallelSession::start(fn (Connection $other): string => $second($this->session($other)));
        $waited = $competitor->awaitsLock($this->connection);
        $this->connection->commit();
        $outcome = $competitor->outcome();

        self::assertTrue($waited, 'the second session must wait for the first session\'s lock; it finished with '.$outcome);

        return [$won, $outcome];
    }

    private function rows(string $sql): int
    {
        return (int) $this->connection->fetchOne($sql);
    }

    public function testConcurrentIdenticalRequestsPostOnlyOneGift(): void
    {
        $record = static fn (GivingSession $session): string => $session->record(self::FIRST, 'CASH', null, 10000, [new Allocation(self::FUND, 10000)], CalendarDate::of(2026, 2, 1));

        [$won, $outcome] = $this->contend($record, $record);

        self::assertSame(self::FIRST, $won);
        self::assertSame('ok:'.self::FIRST, $outcome, 'the waiting request replays the committed gift');
        self::assertSame(1, $this->rows('SELECT COUNT(*) FROM giving_gifts'));
        self::assertSame(1, $this->rows("SELECT COUNT(*) FROM giving_history WHERE action = 'GIFT_POSTED'"));
    }

    public function testConcurrentGiftsCannotShareAnExternalReference(): void
    {
        $record = static fn (string $request): callable => static fn (GivingSession $session): string => $session->record($request, 'BANK', 'EFT-0001', 10000, [new Allocation(self::FUND, 10000)], CalendarDate::of(2026, 2, 1));

        [, $outcome] = $this->contend($record(self::FIRST), $record(self::SECOND));

        self::assertSame('error:Conflict:This fund name, gift reference, reversal or request conflicts with existing Giving evidence. Refresh and check the ledger before retrying.', $outcome);
        self::assertSame(1, $this->rows('SELECT COUNT(*) FROM giving_gifts'));
    }

    public function testConcurrentReversalsCannotCorrectTheSameMoneyTwice(): void
    {
        $this->seedGift();
        $reverse = static fn (string $request): callable => static fn (GivingSession $session): string => $session->reverse($request, self::GIFT, CalendarDate::of(2026, 2, 1));

        [$won, $outcome] = $this->contend($reverse(self::FIRST), $reverse(self::SECOND));

        self::assertSame(self::FIRST, $won);
        self::assertSame('error:Conflict:This gift already has a full reversal.', $outcome);
        self::assertSame(1, $this->rows('SELECT COUNT(*) FROM giving_reversals'));
    }

    public function testConcurrentAssignmentsCannotExceedTheGiftFundBudget(): void
    {
        $this->seedGift();
        $this->seedPledge(self::FIRST);
        $this->seedPledge(self::SECOND);
        $assign = static fn (string $pledge, string $request): callable => static fn (GivingSession $session): string => $session->assign($request, $pledge, self::GIFT, 10000);

        [, $outcome] = $this->contend(
            $assign(self::FIRST, '00000000-0000-4000-8000-0000000001a1'),
            $assign(self::SECOND, '00000000-0000-4000-8000-0000000001a2'),
        );

        self::assertSame('error:Conflict:The assignment exceeds the available gift allocation or remaining pledge target.', $outcome);
        self::assertSame(1, $this->rows('SELECT COUNT(*) FROM giving_pledge_assignments'));
    }

    public function testConcurrentBatchCreationCannotReconcileOneGiftTwice(): void
    {
        $this->seedGift();
        $batch = static fn (string $request, string $reference): callable => static fn (GivingSession $session): string => $session->reconcile($request, $reference, self::GIFT, 10000, CalendarDate::of(2026, 2, 1));

        [, $outcome] = $this->contend($batch(self::FIRST, 'BANK-A'), $batch(self::SECOND, 'BANK-B'));

        self::assertSame('error:Conflict:Every selected gift must be unreconciled, unreversed, in the batch currency and received by the reconciliation date.', $outcome);
        self::assertSame(1, $this->rows('SELECT COUNT(*) FROM giving_reconciliation_batches'));
        self::assertSame(1, $this->rows('SELECT COUNT(*) FROM giving_reconciliation_items'));
    }

    public function testConcurrentIssuesGiveAGiftOneAcknowledgement(): void
    {
        $this->seedGift();
        $acknowledge = static fn (string $request): callable => static fn (GivingSession $session): string => $session->acknowledge($request, self::GIFT);

        [, $outcome] = $this->contend($acknowledge(self::FIRST), $acknowledge(self::SECOND));

        self::assertSame('error:Conflict:This gift already has an acknowledgement. Refresh to open it.', $outcome);
        self::assertSame(1, $this->rows('SELECT COUNT(*) FROM giving_acknowledgements'));
    }

    public function testConcurrentCallbacksForOneChargePostOneOnlineGift(): void
    {
        $charge = '00000000-0000-4000-8000-0000000000c1';
        $post = static fn (GivingSession $session): string => $session->postOnline($charge, self::DONOR, self::FUND, 10000, 'payfast:live:same', CalendarDate::of(2026, 2, 1));

        [$won, $outcome] = $this->contend($post, $post);

        self::assertSame($charge, $won);
        self::assertSame('ok:'.$charge, $outcome, 'the waiting callback replays the committed gift');
        self::assertSame(1, $this->rows('SELECT COUNT(*) FROM giving_gifts'));
        self::assertSame(1, $this->rows('SELECT COUNT(*) FROM giving_payment_sources'));
    }
}
