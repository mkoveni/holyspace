<?php

declare(strict_types=1);

namespace App\Tests\Giving\Infrastructure;

use App\Giving\Application\Contract\ReconciliationItem;
use App\Giving\Domain\Allocation;
use App\Giving\Domain\Gift;
use App\Giving\Domain\GivingException;
use App\Giving\Domain\Money;
use App\Giving\Domain\Reconciliation;
use App\Giving\Infrastructure\Persistence\DbalGiftStore;
use App\Giving\Infrastructure\Persistence\DbalReconciliationReader;
use App\Giving\Infrastructure\Persistence\DbalReconciliationStore;
use App\Shared\Domain\CalendarDate;
use App\Tests\Giving\Application\FakePeople;
use App\Tests\Support\DatabaseTestCase;
use DateTimeImmutable;
use Doctrine\DBAL\Exception\DriverException;

/**
 * The reconciliation integrity the port relies on PostgreSQL for (V20 `giving_check_reconciliation`, `giving_batch_immutable`,
 * unique references and gift membership): the snapshot must match its eligible gifts, and batches and reviews are immutable.
 */
final class ReconciliationEvidenceTest extends DatabaseTestCase
{
    private const string ACTOR = '99999999-9999-4999-8999-999999999999';
    private const string FUND = '00000000-0000-4000-8000-000000000001';
    private const string FIRST = '00000000-0000-4000-8000-0000000000a1';
    private const string SECOND = '00000000-0000-4000-8000-0000000000a2';
    private const string BATCH = '00000000-0000-4000-8000-0000000000b1';
    private const string DONOR = '00000000-0000-4000-8000-0000000000dd';
    private const string HASH = 'cccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccc';

    private DbalReconciliationStore $store;
    private DbalReconciliationReader $reader;

    protected function setUp(): void
    {
        parent::setUp();
        $this->store = new DbalReconciliationStore($this->connection);
        $people = new FakePeople();
        $people->add(self::DONOR);
        $this->reader = new DbalReconciliationReader($this->connection, $people);
        $this->connection->insert('giving_funds', ['id' => self::FUND, 'name' => 'General', 'description' => '', 'request_hash' => str_repeat('a', 64)]);
        $this->connection->transactional(function (): void {
            $gifts = new DbalGiftStore($this->connection);
            foreach ([[self::FIRST, 10000, 5, self::DONOR], [self::SECOND, 2500, 6, null]] as [$id, $amount, $day, $donor]) {
                $gifts->insert(Gift::record($id, $donor, Money::of($amount, 'ZAR'), CalendarDate::of(2026, 1, $day), 'BANK', null, [new Allocation(self::FUND, $amount)], self::ACTOR, new DateTimeImmutable('2026-01-05T08:00:00Z')), str_repeat((string) $day, 64));
            }
        });
    }

    /** @param list<string> $gifts */
    private function batch(array $gifts = [self::FIRST, self::SECOND], int $actual = 12500, string $reference = 'BANK-001', string $id = self::BATCH, string $on = '2026-02-01'): Reconciliation
    {
        return Reconciliation::create($id, $reference, 12500, $actual, 'ZAR', CalendarDate::parse($on), 'x', self::ACTOR, new DateTimeImmutable('2026-02-01T08:00:00Z'), $gifts);
    }

    private function created(): void
    {
        $this->connection->transactional(fn () => $this->store->insert($this->batch(), self::HASH));
    }

    private function assertRefused(Reconciliation $batch, int $remaining = 0): void
    {
        $this->connection->beginTransaction();
        try {
            $this->store->insert($batch, self::HASH);
            self::fail('the store accepted the batch');
        } catch (GivingException $error) {
            self::assertStringStartsWith('This fund name, gift reference, reversal or request conflicts', $error->getMessage());
        } finally {
            $this->connection->rollBack();
        }
        self::assertSame($remaining, (int) $this->connection->fetchOne('SELECT count(*) FROM giving_reconciliation_batches'));
    }

    private function sqlState(callable $statement): string
    {
        try {
            $statement();
        } catch (DriverException $error) {
            return (string) $error->getSQLState();
        }
        self::fail('the database accepted the statement');
    }

    private function reverse(string $gift): void
    {
        $this->connection->insert('giving_reversals', ['id' => '00000000-0000-4000-8000-0000000000e1', 'gift_id' => $gift, 'reversed_on' => '2026-02-05', 'reason' => 'Duplicate', 'request_hash' => self::HASH, 'created_by' => self::ACTOR, 'created_at' => '2026-02-05 08:00:00+00']);
    }

    public function testTheStoreRoundTripsTheBatchAndTracksReviewsAndReversals(): void
    {
        $this->created();

        self::assertEquals($this->batch(), $this->store->find(self::BATCH));
        self::assertNull($this->store->find('00000000-0000-4000-8000-0000000000b9'));
        self::assertSame([true, false], [$this->store->reconciled(self::FIRST), $this->store->reconciled('00000000-0000-4000-8000-0000000000ff')]);
        self::assertSame([0, 0], [$this->store->version(self::BATCH), $this->store->reversedMinor(self::BATCH)]);
        $this->store->lock(self::BATCH);
        self::assertSame(self::BATCH, $this->store->createdBy(self::BATCH, self::HASH));

        $review = $this->batch()->review('00000000-0000-4000-8000-0000000000c1', 0, 'EXPLAINED', 'Verified', 0, self::ACTOR, new DateTimeImmutable('2026-02-02T08:00:00Z'));
        $this->store->insertReview($review, self::HASH);
        self::assertSame(1, $this->store->version(self::BATCH));
        self::assertSame($review->id, $this->store->reviewedBy($review->id, self::HASH));
        $this->reverse(self::SECOND);
        self::assertSame(2500, $this->store->reversedMinor(self::BATCH));

        $this->expectException(GivingException::class);
        $this->expectExceptionMessage('This fund name, gift reference, reversal or request conflicts');
        $this->store->insertReview($this->batch()->review('00000000-0000-4000-8000-0000000000c2', 0, 'FOLLOW_UP', 'Duplicate version', 0, self::ACTOR, new DateTimeImmutable()), self::HASH);
    }

    public function testTheReaderReportsReversalsAndReopensTheReview(): void
    {
        $this->created();
        $this->store->insertReview($this->batch()->review('00000000-0000-4000-8000-0000000000c1', 0, 'EXPLAINED', 'Verified', 0, self::ACTOR, new DateTimeImmutable('2026-02-02T08:00:00Z')), self::HASH);

        $detail = $this->reader->detail(self::BATCH);
        self::assertNotNull($detail);
        self::assertSame([false, 1, 12500, 0], [$detail->summary->needsReview, $detail->summary->version, $detail->summary->currentNetMinor, $detail->summary->reversedMinor]);
        self::assertSame([self::FIRST, self::SECOND], $detail->summary->batch->giftIds, 'ordered by received date');
        self::assertSame(['Donor Mabasa', 'Anonymous'], array_map(static fn (ReconciliationItem $item): string => $item->donorName, $detail->items));

        $this->reverse(self::SECOND);
        $detail = $this->reader->detail(self::BATCH);
        self::assertNotNull($detail);
        self::assertSame([true, 2500, 10000], [$detail->summary->needsReview, $detail->summary->reversedMinor, $detail->summary->currentNetMinor]);
        self::assertTrue($detail->items[1]->reversed);
        self::assertSame(12500, $detail->summary->batch->actualMinor, 'the original snapshot is kept');

        $page = $this->reader->list(25, 0);
        self::assertSame(1, $page->total);
        self::assertEquals($detail->summary, $page->items[0]);
        self::assertNull($this->reader->detail('00000000-0000-4000-8000-0000000000b9'));
    }

    public function testTheSnapshotMustMatchEligibleGifts(): void
    {
        $this->assertRefused($this->batch(actual: 12000));
        $this->assertRefused($this->batch([self::FIRST, self::SECOND], on: '2026-01-05'));
        $this->reverse(self::SECOND);
        $this->assertRefused($this->batch());
    }

    public function testAGiftAndAReferenceBelongToOneBatch(): void
    {
        $this->created();

        $this->assertRefused($this->batch([self::FIRST], 10000, 'BANK-002', '00000000-0000-4000-8000-0000000000b2'), 1);
        $this->assertRefused($this->batch([self::SECOND], 2500, ' bank-001 ', '00000000-0000-4000-8000-0000000000b2'), 1);
    }

    public function testBatchesItemsAndReviewsAreImmutable(): void
    {
        $this->created();
        $this->store->insertReview($this->batch()->review('00000000-0000-4000-8000-0000000000c1', 0, 'EXPLAINED', 'Verified', 0, self::ACTOR, new DateTimeImmutable()), self::HASH);

        foreach ([
            fn (): int|string => $this->connection->update('giving_reconciliation_batches', ['reference' => 'Changed'], ['id' => self::BATCH]),
            fn (): int|string => $this->connection->delete('giving_reconciliation_items', ['batch_id' => self::BATCH]),
            fn (): int|string => $this->connection->update('giving_reconciliation_reviews', ['note' => 'Changed'], ['batch_id' => self::BATCH]),
            fn (): int|string => $this->connection->delete('giving_reconciliation_reviews', ['batch_id' => self::BATCH]),
        ] as $statement) {
            self::assertSame('23514', $this->sqlState($statement));
        }
    }
}
