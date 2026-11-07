<?php

declare(strict_types=1);

namespace App\Tests\Giving\Infrastructure;

use App\Giving\Domain\Acknowledgement;
use App\Giving\Domain\AcknowledgementLine;
use App\Giving\Domain\Allocation;
use App\Giving\Domain\Gift;
use App\Giving\Domain\GivingException;
use App\Giving\Domain\Money;
use App\Giving\Infrastructure\Persistence\DbalAcknowledgementReader;
use App\Giving\Infrastructure\Persistence\DbalAcknowledgementStore;
use App\Giving\Infrastructure\Persistence\DbalGiftStore;
use App\Shared\Domain\CalendarDate;
use App\Tests\Support\DatabaseTestCase;
use DateTimeImmutable;
use Doctrine\DBAL\Exception\DriverException;

/**
 * The acknowledgement integrity the port relies on PostgreSQL for (V20/V21 `giving_check_acknowledgement`,
 * `giving_ack_immutable`): lines must match the gift's allocations, no reversed or refunded gift, immutable evidence.
 */
final class AcknowledgementEvidenceTest extends DatabaseTestCase
{
    private const string ACTOR = '99999999-9999-4999-8999-999999999999';
    private const string FUND = '00000000-0000-4000-8000-000000000001';
    private const string OTHER = '00000000-0000-4000-8000-000000000002';
    private const string GIFT = '00000000-0000-4000-8000-0000000000aa';
    private const string ACK = '00000000-0000-4000-8000-0000000000c1';
    private const string HASH = 'cccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccc';

    private DbalAcknowledgementStore $store;
    private DbalAcknowledgementReader $reader;

    protected function setUp(): void
    {
        parent::setUp();
        $this->store = new DbalAcknowledgementStore($this->connection);
        $this->reader = new DbalAcknowledgementReader($this->connection);
        $this->connection->insert('giving_funds', ['id' => self::FUND, 'name' => 'Zion building', 'description' => '', 'request_hash' => str_repeat('a', 64)]);
        $this->connection->insert('giving_funds', ['id' => self::OTHER, 'name' => 'Alms', 'description' => '', 'request_hash' => str_repeat('b', 64)]);
        $this->connection->transactional(function (): void {
            (new DbalGiftStore($this->connection))->insert($this->gift([new Allocation(self::FUND, 7000), new Allocation(self::OTHER, 3000)]), str_repeat('d', 64));
        });
    }

    /** @param list<Allocation> $allocations */
    private function gift(array $allocations): Gift
    {
        $amount = array_sum(array_map(static fn (Allocation $allocation): int => $allocation->amountMinor, $allocations));

        return Gift::reconstitute(self::GIFT, null, Money::of($amount, 'ZAR'), CalendarDate::of(2026, 1, 5), 'BANK', 'BANK-7', $allocations, self::ACTOR, new DateTimeImmutable('2026-01-05T08:00:00Z'));
    }

    /** @param list<Allocation>|null $allocations */
    private function acknowledgement(?array $allocations = null, string $id = self::ACK): Acknowledgement
    {
        return Acknowledgement::issue(
            $id,
            $this->gift($allocations ?? [new Allocation(self::FUND, 7000), new Allocation(self::OTHER, 3000)]),
            'Anonymous',
            [self::FUND => 'Zion building', self::OTHER => 'Alms'],
            CalendarDate::of(2026, 1, 6),
            self::ACTOR,
            new DateTimeImmutable('2026-01-06T08:00:00Z'),
        );
    }

    private function issued(): void
    {
        $this->connection->transactional(fn () => $this->store->insert($this->acknowledgement(), self::HASH));
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

    private function assertRefused(Acknowledgement $acknowledgement, int $remaining = 0): void
    {
        $this->connection->beginTransaction();
        try {
            $this->store->insert($acknowledgement, self::HASH);
            self::fail('the store accepted the acknowledgement');
        } catch (GivingException $error) {
            self::assertStringStartsWith('This fund name, gift reference, reversal or request conflicts', $error->getMessage());
        } finally {
            $this->connection->rollBack();
        }
        self::assertSame($remaining, (int) $this->connection->fetchOne('SELECT count(*) FROM giving_acknowledgements'));
    }

    public function testTheReaderReturnsTheNumberedFrozenSnapshot(): void
    {
        $this->issued();
        $number = (int) $this->connection->fetchOne('SELECT number FROM giving_acknowledgements');

        $view = $this->reader->get(self::ACK);
        self::assertNotNull($view);
        self::assertEquals($view, $this->reader->forGift(self::GIFT));
        self::assertSame(\sprintf('G-%08d', $number), $view->number);
        self::assertMatchesRegularExpression('/^G-\d{8}$/', $view->number);
        self::assertSame([self::GIFT, 'Anonymous', '2026-01-06', '2026-01-05', 10000, 'ZAR', 'BANK', 'BANK-7'], [
            $view->giftId, $view->donorName, $view->issuedOn->iso(), $view->receivedOn->iso(), $view->amountMinor, $view->currency, $view->method, $view->reference,
        ]);
        self::assertSame([false, null, null], [$view->voided, $view->reversedOn, $view->reversalReason]);
        self::assertEquals([new AcknowledgementLine(self::OTHER, 'Alms', 3000), new AcknowledgementLine(self::FUND, 'Zion building', 7000)], $view->lines, 'ordered by fund name');
        self::assertSame(self::ACK, $this->store->forGift(self::GIFT));
        self::assertSame(self::ACK, $this->store->issuedBy(self::ACK, self::HASH));
        self::assertNull($this->store->issuedBy('00000000-0000-4000-8000-0000000000c2', self::HASH));
        self::assertNull($this->reader->get('00000000-0000-4000-8000-0000000000c2'));

        $this->expectException(GivingException::class);
        $this->expectExceptionMessage('This request identifier was already used.');
        $this->store->issuedBy(self::ACK, str_repeat('e', 64));
    }

    public function testFundRenamesDoNotChangeTheSnapshotAndAReversalVoidsIt(): void
    {
        $this->issued();
        $this->connection->update('giving_funds', ['name' => 'Renamed', 'version' => 1], ['id' => self::FUND]);
        $this->connection->insert('giving_reversals', ['id' => '00000000-0000-4000-8000-0000000000bb', 'gift_id' => self::GIFT, 'reversed_on' => '2026-02-01', 'reason' => 'Duplicate', 'request_hash' => self::HASH, 'created_by' => self::ACTOR, 'created_at' => '2026-02-01 08:00:00+00']);

        $view = $this->reader->get(self::ACK);
        self::assertNotNull($view);
        self::assertSame([true, '2026-02-01', 'Duplicate'], [$view->voided, $view->reversedOn?->iso(), $view->reversalReason]);
        self::assertSame(['Alms', 'Zion building'], array_map(static fn (AcknowledgementLine $line): string => $line->fundName, $view->lines));
    }

    public function testAProviderRefundVoidsItAndTakesPrecedenceAndARefundedGiftCannotBeAcknowledged(): void
    {
        $online = '00000000-0000-4000-8000-0000000000ac';
        $gift = Gift::reconstitute($online, null, Money::of(5000, 'ZAR'), CalendarDate::of(2026, 1, 10), 'ONLINE', null, [new Allocation(self::FUND, 5000)], self::ACTOR, new DateTimeImmutable('2026-01-10T08:00:00Z'));
        $acknowledge = static fn (string $id): Acknowledgement => Acknowledgement::issue($id, $gift, 'Anonymous', [self::FUND => 'Zion building'], CalendarDate::of(2026, 1, 11), self::ACTOR, new DateTimeImmutable('2026-01-11T08:00:00Z'));
        $this->connection->transactional(function () use ($gift, $online, $acknowledge): void {
            (new DbalGiftStore($this->connection))->insert($gift, str_repeat('f', 64));
            $this->connection->insert('giving_payment_sources', ['gift_id' => $online, 'charge_id' => '00000000-0000-4000-8000-0000000000e9', 'reference' => 'CHG-1']);
            $this->store->insert($acknowledge(self::ACK), self::HASH);
        });
        foreach (['2026-02-05' => 'e1', '2026-02-03' => 'e2'] as $date => $id) {
            $this->connection->insert('giving_payment_refunds', [
                'id' => '00000000-0000-4000-8000-0000000000'.$id, 'gift_id' => $online, 'fund_id' => self::FUND, 'amount_minor' => 1000,
                'refunded_on' => $date, 'reference' => 'RF-'.$id, 'created_by' => self::ACTOR, 'created_at' => $date.' 08:00:00+00',
            ]);
        }

        $view = $this->reader->forGift($online);
        self::assertNotNull($view);
        self::assertSame([true, '2026-02-03', 'Provider-completed refund', 'ONLINE'], [$view->voided, $view->reversedOn?->iso(), $view->reversalReason, $view->method]);

        $this->assertRefused($acknowledge('00000000-0000-4000-8000-0000000000c2'), 1);
    }

    public function testLinesThatDoNotMatchTheGiftAreRefusedAndTranslated(): void
    {
        $this->assertRefused($this->acknowledgement([new Allocation(self::FUND, 6000), new Allocation(self::OTHER, 4000)]));
        $this->assertRefused($this->acknowledgement([new Allocation(self::FUND, 7000)]));
    }

    public function testAReversedGiftCannotBeAcknowledgedInTheDatabase(): void
    {
        $this->connection->insert('giving_reversals', ['id' => '00000000-0000-4000-8000-0000000000bb', 'gift_id' => self::GIFT, 'reversed_on' => '2026-02-01', 'reason' => 'Duplicate', 'request_hash' => self::HASH, 'created_by' => self::ACTOR, 'created_at' => '2026-02-01 08:00:00+00']);

        $this->assertRefused($this->acknowledgement());
    }

    public function testAcknowledgementsAndLinesAreImmutable(): void
    {
        $this->issued();

        self::assertSame('23514', $this->sqlState(fn (): int|string => $this->connection->update('giving_acknowledgements', ['donor_name' => 'Someone else'], ['id' => self::ACK])));
        self::assertSame('23514', $this->sqlState(fn (): int|string => $this->connection->update('giving_acknowledgement_lines', ['fund_name' => 'Renamed'], ['acknowledgement_id' => self::ACK])));
        self::assertSame('23514', $this->sqlState(fn (): int|string => $this->connection->delete('giving_acknowledgement_lines', ['acknowledgement_id' => self::ACK])));
        self::assertSame('23514', $this->sqlState(fn (): int|string => $this->connection->delete('giving_acknowledgements', ['id' => self::ACK])));
        $this->assertRefused($this->acknowledgement(id: '00000000-0000-4000-8000-0000000000c2'), 1);
    }

    public function testTwoAcknowledgementsInOneTransactionAreEachCheckedWithTheirOwnLines(): void
    {
        $second = '00000000-0000-4000-8000-0000000000ab';
        $this->connection->transactional(function () use ($second): void {
            (new DbalGiftStore($this->connection))->insert(Gift::reconstitute($second, null, Money::of(500, 'ZAR'), CalendarDate::of(2026, 1, 6), 'CASH', null, [new Allocation(self::OTHER, 500)], self::ACTOR, new DateTimeImmutable('2026-01-06T08:00:00Z')), str_repeat('f', 64));
            $this->store->insert($this->acknowledgement(), self::HASH);
            $this->store->insert(Acknowledgement::issue('00000000-0000-4000-8000-0000000000c2', Gift::reconstitute($second, null, Money::of(500, 'ZAR'), CalendarDate::of(2026, 1, 6), 'CASH', null, [new Allocation(self::OTHER, 500)], self::ACTOR, new DateTimeImmutable('2026-01-06T08:00:00Z')), 'Anonymous', [self::OTHER => 'Alms'], CalendarDate::of(2026, 1, 6), self::ACTOR, new DateTimeImmutable('2026-01-06T08:00:00Z')), self::HASH);
        });

        self::assertSame(2, (int) $this->connection->fetchOne('SELECT count(*) FROM giving_acknowledgements'));
    }
}
