<?php

declare(strict_types=1);

namespace App\Tests\Giving\Infrastructure;

use App\Giving\Domain\Allocation;
use App\Giving\Domain\Gift;
use App\Giving\Domain\GivingException;
use App\Giving\Domain\Money;
use App\Giving\Infrastructure\Persistence\DbalGiftStore;
use App\Shared\Domain\CalendarDate;
use App\Tests\Support\DatabaseTestCase;
use DateTimeImmutable;
use Doctrine\DBAL\Exception\DriverException;

/** The integrity the port relies on PostgreSQL for: balanced allocations, immutable evidence, one full reversal. */
final class GiftEvidenceTriggersTest extends DatabaseTestCase
{
    private const string ACTOR = '99999999-9999-4999-8999-999999999999';
    private const string FUND = '00000000-0000-4000-8000-000000000001';
    private const string GIFT = '00000000-0000-4000-8000-0000000000aa';

    protected function setUp(): void
    {
        parent::setUp();
        $this->connection->insert('giving_funds', ['id' => self::FUND, 'name' => 'General', 'description' => '', 'request_hash' => str_repeat('a', 64)]);
    }

    private function store(): DbalGiftStore
    {
        return new DbalGiftStore($this->connection);
    }

    private function gift(string $id = self::GIFT, ?string $reference = null): Gift
    {
        return Gift::record($id, null, Money::of(10000, 'ZAR'), CalendarDate::of(2026, 1, 1), 'BANK', $reference, [new Allocation(self::FUND, 10000)], self::ACTOR, new DateTimeImmutable('2026-01-01T08:00:00Z'));
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

    public function testAHeaderWithoutAllocationsIsRejectedAtCommit(): void
    {
        self::assertSame('23514', $this->sqlState(fn (): int|string => $this->connection->insert('giving_gifts', [
            'id' => self::GIFT, 'amount_minor' => 100, 'currency' => 'ZAR', 'received_on' => '2026-01-01', 'method' => 'BANK',
            'request_hash' => str_repeat('a', 64), 'created_by' => self::ACTOR, 'created_at' => '2026-01-01 08:00:00+00',
        ])));
        self::assertSame(0, (int) $this->connection->fetchOne('SELECT count(*) FROM giving_gifts'));
    }

    public function testTheStoreChecksTheBalanceBeforeCommitAndTranslatesIt(): void
    {
        $this->connection->beginTransaction();
        try {
            $this->connection->insert('giving_gifts', [
                'id' => self::GIFT, 'amount_minor' => 100, 'currency' => 'ZAR', 'received_on' => '2026-01-01', 'method' => 'BANK',
                'request_hash' => str_repeat('a', 64), 'created_by' => self::ACTOR, 'created_at' => '2026-01-01 08:00:00+00',
            ]);
            // A later balanced gift in the same transaction makes the store check the earlier, unbalanced one too.
            $this->expectException(GivingException::class);
            $this->expectExceptionMessage('This fund name, gift reference, reversal or request conflicts with existing Giving evidence.');
            $this->store()->insert($this->gift('00000000-0000-4000-8000-0000000000ab'), str_repeat('b', 64));
        } finally {
            $this->connection->rollBack();
            self::assertSame(0, (int) $this->connection->fetchOne('SELECT count(*) FROM giving_gifts'));
        }
    }

    public function testPostedEvidenceIsImmutable(): void
    {
        $this->connection->transactional(fn () => $this->store()->insert($this->gift(), str_repeat('b', 64)));

        self::assertSame('23514', $this->sqlState(fn (): int|string => $this->connection->update('giving_gifts', ['amount_minor' => 20000], ['id' => self::GIFT])));
        self::assertSame('23514', $this->sqlState(fn (): int|string => $this->connection->delete('giving_allocations', ['gift_id' => self::GIFT])));
        self::assertSame(10000, (int) $this->connection->fetchOne('SELECT amount_minor FROM giving_gifts'));
    }

    public function testDuplicateReferencesAndSecondReversalsAreEvidenceConflicts(): void
    {
        $this->connection->transactional(fn () => $this->store()->insert($this->gift(reference: 'BANK-1'), str_repeat('b', 64)));
        $gift = $this->store()->findForUpdate(self::GIFT);
        self::assertNotNull($gift);
        self::assertSame('BANK-1', $gift->externalReference);
        self::assertSame(10000, $gift->allocations[0]->amountMinor);

        foreach ([
            fn () => $this->store()->insert($this->gift('00000000-0000-4000-8000-0000000000ab', ' bank-1 '), str_repeat('c', 64)),
            fn () => $this->store()->insertReversal($gift->reverse('00000000-0000-4000-8000-0000000000bc', CalendarDate::of(2026, 1, 2), 'Second', self::ACTOR, new DateTimeImmutable()), str_repeat('e', 64)),
        ] as $index => $write) {
            if (1 === $index) {
                $this->store()->insertReversal($gift->reverse('00000000-0000-4000-8000-0000000000bb', CalendarDate::of(2026, 1, 2), 'First', self::ACTOR, new DateTimeImmutable()), str_repeat('d', 64));
                self::assertTrue($this->store()->hasReversal(self::GIFT));
            }
            try {
                $this->connection->transactional($write);
                self::fail('accepted conflicting evidence');
            } catch (GivingException $error) {
                self::assertStringStartsWith('This fund name, gift reference, reversal or request conflicts', $error->getMessage());
            }
        }
        self::assertSame(1, (int) $this->connection->fetchOne('SELECT count(*) FROM giving_gifts'));
        self::assertSame(1, (int) $this->connection->fetchOne('SELECT count(*) FROM giving_reversals'));
    }
}
