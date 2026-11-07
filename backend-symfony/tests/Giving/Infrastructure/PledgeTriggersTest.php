<?php

declare(strict_types=1);

namespace App\Tests\Giving\Infrastructure;

use App\Giving\Domain\Allocation;
use App\Giving\Domain\Gift;
use App\Giving\Domain\GivingException;
use App\Giving\Domain\Money;
use App\Giving\Domain\Pledge;
use App\Giving\Domain\PledgeAssignment;
use App\Giving\Infrastructure\Persistence\DbalGiftStore;
use App\Giving\Infrastructure\Persistence\DbalPledgeStore;
use App\Shared\Domain\CalendarDate;
use App\Tests\Support\DatabaseTestCase;
use DateTimeImmutable;
use Doctrine\DBAL\Exception\DriverException;

/**
 * The pledge integrity the port relies on PostgreSQL for (V20 `giving_assignment_guard`, `giving_pledge_guard`): capacity and
 * eligibility on insert, ACTIVE->CANCELLED as the only assignment change, immutable commitments and no deletes.
 */
final class PledgeTriggersTest extends DatabaseTestCase
{
    private const string ACTOR = '99999999-9999-4999-8999-999999999999';
    private const string FUND = '00000000-0000-4000-8000-000000000001';
    private const string GIFT = '00000000-0000-4000-8000-0000000000aa';
    private const string PLEDGE = '00000000-0000-4000-8000-0000000000a1';
    private const string DONOR = '00000000-0000-4000-8000-0000000000dd';
    private const string HASH = 'cccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccc';

    private DbalPledgeStore $store;

    protected function setUp(): void
    {
        parent::setUp();
        $this->store = new DbalPledgeStore($this->connection);
        $this->connection->insert('giving_funds', ['id' => self::FUND, 'name' => 'General', 'description' => '', 'request_hash' => str_repeat('a', 64)]);
        $this->connection->transactional(function (): void {
            (new DbalGiftStore($this->connection))->insert(Gift::record(self::GIFT, self::DONOR, Money::of(10000, 'ZAR'), CalendarDate::of(2026, 1, 5), 'BANK', null, [new Allocation(self::FUND, 10000)], self::ACTOR, new DateTimeImmutable('2026-01-05T08:00:00Z')), str_repeat('b', 64));
        });
        $this->store->insert(Pledge::create(self::PLEDGE, self::DONOR, self::FUND, Money::of(20000, 'ZAR'), CalendarDate::of(2026, 1, 1), CalendarDate::of(2026, 12, 31), self::ACTOR, new DateTimeImmutable('2026-01-01T08:00:00Z')), self::HASH);
    }

    private function assignment(int $amount, string $id = '00000000-0000-4000-8000-0000000000c1'): PledgeAssignment
    {
        return PledgeAssignment::record($id, self::PLEDGE, self::GIFT, self::FUND, self::DONOR, 'ZAR', $amount, self::ACTOR, new DateTimeImmutable('2026-02-01T08:00:00Z'));
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

    public function testTheGuardRefusesAnAssignmentAboveTheAvailableAllocationAndTheStoreTranslatesIt(): void
    {
        $this->store->insertAssignment($this->assignment(6000), self::HASH);

        $this->expectException(GivingException::class);
        $this->expectExceptionMessage('This fund name, gift reference, reversal or request conflicts with existing Giving evidence.');

        try {
            $this->store->insertAssignment($this->assignment(4001, '00000000-0000-4000-8000-0000000000c2'), self::HASH);
        } finally {
            self::assertSame(6000, $this->store->assigned(self::GIFT, self::FUND));
        }
    }

    public function testCancellingReleasesCapacityAndIsTheOnlyChangeAllowed(): void
    {
        $active = $this->assignment(10000);
        $this->store->insertAssignment($active, self::HASH);
        self::assertSame(10000, $this->store->credited(self::PLEDGE));

        $cancelled = $active->cancel(0, 'Wrong pledge', self::ACTOR, new DateTimeImmutable('2026-02-02T08:00:00Z'));
        $this->store->saveAssignment($cancelled);
        self::assertEquals($cancelled, $this->store->findAssignment($active->id));
        self::assertSame(0, $this->store->assigned(self::GIFT, self::FUND));

        self::assertSame('23514', $this->sqlState(fn (): int|string => $this->connection->executeStatement(
            "UPDATE giving_pledge_assignments SET status = 'ACTIVE', version = version + 1 WHERE id = ?",
            [$active->id],
        )));
        self::assertSame('23514', $this->sqlState(fn (): int|string => $this->connection->delete('giving_pledge_assignments', ['id' => $active->id])));
        $this->expectException(GivingException::class);
        $this->expectExceptionMessage('This assignment changed.');
        $this->store->saveAssignment($cancelled);
    }

    public function testAReversedGiftIsNotEligibleAndNoLongerCredits(): void
    {
        $this->store->insertAssignment($this->assignment(3000), self::HASH);
        $this->connection->insert('giving_reversals', ['id' => '00000000-0000-4000-8000-0000000000bb', 'gift_id' => self::GIFT, 'reversed_on' => '2026-02-01', 'reason' => 'Duplicate', 'request_hash' => self::HASH, 'created_by' => self::ACTOR, 'created_at' => '2026-02-01 08:00:00+00']);

        self::assertSame(0, $this->store->credited(self::PLEDGE));
        self::assertSame('23514', $this->sqlState(fn () => $this->connection->insert('giving_pledge_assignments', [
            'id' => '00000000-0000-4000-8000-0000000000c2', 'pledge_id' => self::PLEDGE, 'gift_id' => self::GIFT, 'fund_id' => self::FUND, 'donor_id' => self::DONOR,
            'currency' => 'ZAR', 'amount_minor' => 100, 'status' => 'ACTIVE', 'version' => 0, 'request_hash' => self::HASH, 'created_by' => self::ACTOR, 'created_at' => '2026-02-02 08:00:00+00',
        ])));
    }

    public function testCommitmentsAreImmutableVersionedAndNeverDeleted(): void
    {
        $pledge = $this->store->findForUpdate(self::PLEDGE);
        self::assertNotNull($pledge);
        $this->store->save($pledge->revised());
        $cancelled = $pledge->revised()->cancel(1, 'Withdrawn');
        $this->store->save($cancelled);
        self::assertEquals($cancelled, $this->store->findForUpdate(self::PLEDGE));

        self::assertSame('23514', $this->sqlState(fn (): int|string => $this->connection->executeStatement("UPDATE giving_pledges SET amount_minor = 1, version = version + 1 WHERE id = '".self::PLEDGE."'")));
        self::assertSame('23514', $this->sqlState(fn (): int|string => $this->connection->executeStatement("UPDATE giving_pledges SET status = 'OPEN', version = version + 1 WHERE id = '".self::PLEDGE."'")));
        self::assertSame('23514', $this->sqlState(fn (): int|string => $this->connection->delete('giving_pledges', ['id' => self::PLEDGE])));
        $this->expectException(GivingException::class);
        $this->expectExceptionMessage('The pledge changed. Refresh before trying again.');
        $this->store->save($cancelled);
    }
}
