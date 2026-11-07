<?php

declare(strict_types=1);

namespace App\Tests\Giving\Domain;

use App\Giving\Domain\Allocation;
use App\Giving\Domain\Gift;
use App\Giving\Domain\GivingException;
use App\Giving\Domain\Money;
use App\Shared\Domain\CalendarDate;
use App\Shared\Domain\ErrorKind;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class GiftTest extends TestCase
{
    private const string GIFT = '00000000-0000-4000-8000-0000000000aa';
    private const string FUND = '00000000-0000-4000-8000-000000000001';
    private const string OTHER = '00000000-0000-4000-8000-000000000002';
    private const string ACTOR = '99999999-9999-4999-8999-999999999999';
    private const string DONOR = '00000000-0000-4000-8000-0000000000dd';
    private const string REFUND = '00000000-0000-4000-8000-0000000000e1';

    /** @param list<Allocation|null>|null $allocations */
    private function gift(?array $allocations, ?string $method = 'BANK', ?string $reference = null, string $id = self::GIFT): Gift
    {
        return Gift::record($id, null, Money::of(10000, 'ZAR'), CalendarDate::of(2026, 1, 1), $method, $reference, $allocations, self::ACTOR, new DateTimeImmutable('2026-01-02T10:00:00Z'));
    }

    private function expectInvalid(string $message): void
    {
        $this->expectException(GivingException::class);
        $this->expectExceptionMessage($message);
    }

    public function testAGiftKeepsItsAllocationsAndNormalisesTheReference(): void
    {
        $gift = $this->gift([new Allocation(self::OTHER, 3000), new Allocation(self::FUND, 7000)], reference: '  BANK-1 ');

        self::assertSame('BANK-1', $gift->externalReference);
        self::assertSame([self::OTHER, self::FUND], array_map(static fn (Allocation $a): string => $a->fundId, $gift->allocations));
        self::assertSame([self::FUND, self::OTHER], array_map(static fn (Allocation $a): string => $a->fundId, $gift->allocationsByFund()));
        self::assertNull($this->gift([new Allocation(self::FUND, 10000)], reference: '  ')->externalReference);
    }

    public function testAllocationsMustBalanceExactly(): void
    {
        $this->expectInvalid('Fund allocations must equal the full gift amount.');

        $this->gift([new Allocation(self::FUND, 9999)]);
    }

    /** @return iterable<string, array{list<Allocation>}> */
    public static function badAllocations(): iterable
    {
        yield 'duplicate fund' => [[new Allocation(self::FUND, 5000), new Allocation(self::FUND, 5000)]];
        yield 'zero' => [[new Allocation(self::FUND, 0), new Allocation(self::OTHER, 10000)]];
        yield 'above the gift' => [[new Allocation(self::FUND, 10001)]];
        yield 'missing fund' => [[new Allocation('', 10000)]];
    }

    /** @param list<Allocation> $allocations */
    #[DataProvider('badAllocations')]
    public function testEachFundOnceWithAPositiveAllocation(array $allocations): void
    {
        $this->expectInvalid('Choose each fund once with a positive allocation.');

        $this->gift($allocations);
    }

    public function testOneToTwentyAllocations(): void
    {
        foreach ([null, [], array_fill(0, 21, new Allocation(self::FUND, 1))] as $allocations) {
            try {
                $this->gift($allocations);
                self::fail('accepted '.\count($allocations ?? []).' allocations');
            } catch (GivingException $error) {
                self::assertSame('Allocate the gift to 1–20 funds.', $error->getMessage());
            }
        }
    }

    public function testEveryAllocationIsPresent(): void
    {
        $this->expectInvalid('Every allocation needs a fund and an amount.');

        $this->gift([null]);
    }

    public function testTheMethodIsCashBankOtherOrOnline(): void
    {
        self::assertSame('ONLINE', $this->gift([new Allocation(self::FUND, 10000)], 'ONLINE')->method);
        $this->expectInvalid('Choose cash, bank or other. Online payments require a verified payment integration.');

        $this->gift([new Allocation(self::FUND, 10000)], 'CARD');
    }

    public function testTheReferenceIsAtMost200Characters(): void
    {
        self::assertSame(200, \strlen((string) $this->gift([new Allocation(self::FUND, 10000)], reference: str_repeat('r', 200))->externalReference));
        $this->expectInvalid('Reference is required and must be at most 200 characters.');

        $this->gift([new Allocation(self::FUND, 10000)], reference: str_repeat('r', 201));
    }

    public function testAReceivedDateAndIdAreRequired(): void
    {
        foreach ([
            static fn (): Gift => Gift::record(self::GIFT, null, Money::of(1, 'ZAR'), null, 'BANK', null, [new Allocation(self::FUND, 1)], self::ACTOR, new DateTimeImmutable()),
            fn (): Gift => $this->gift([new Allocation(self::FUND, 10000)], id: ''),
        ] as $attempt) {
            try {
                $attempt();
                self::fail('accepted an incomplete gift');
            } catch (GivingException $error) {
                self::assertSame('Complete the gift details.', $error->getMessage());
            }
        }
    }

    public function testAReversalRetainsTheOriginalAndRequiresChronologyAndAReason(): void
    {
        $gift = $this->gift([new Allocation(self::FUND, 10000)]);
        $at = new DateTimeImmutable('2026-01-03T10:00:00Z');

        try {
            $gift->reverse('r1', CalendarDate::of(2025, 12, 31), 'Correction', self::ACTOR, $at);
            self::fail('accepted a reversal before the received date');
        } catch (GivingException $error) {
            self::assertSame("A reversal cannot precede the gift's received date.", $error->getMessage());
        }
        try {
            $gift->reverse('r1', CalendarDate::of(2026, 1, 2), ' ', self::ACTOR, $at);
            self::fail('accepted a blank reason');
        } catch (GivingException $error) {
            self::assertSame('Reversal reason is required and must be at most 1000 characters.', $error->getMessage());
        }

        $reversal = $gift->reverse('r1', CalendarDate::of(2026, 1, 1), ' Duplicate recorded ', self::ACTOR, $at);
        self::assertSame(self::GIFT, $reversal->giftId);
        self::assertSame('r1', $reversal->id);
        self::assertSame('Duplicate recorded', $reversal->reason);
        self::assertSame(10000, $gift->money->amountMinor);
    }

    private function online(int $amount = 10000, string $reference = 'payfast:live:pf_1'): Gift
    {
        return Gift::online(self::GIFT, self::DONOR, self::FUND, $amount, $reference, CalendarDate::of(2026, 1, 10), self::ACTOR, new DateTimeImmutable('2026-01-10T10:00:00Z'));
    }

    public function testAnOnlineGiftIsZarToOneFundAndKeepsTheProviderReference(): void
    {
        $gift = $this->online();

        self::assertSame([self::GIFT, self::DONOR, 'ONLINE', 'ZAR', 10000, 'payfast:live:pf_1'], [
            $gift->id, $gift->donorId, $gift->method, $gift->money->currency, $gift->money->amountMinor, $gift->externalReference,
        ]);
        self::assertEquals([new Allocation(self::FUND, 10000)], $gift->allocations);
    }

    public function testAnOnlineGiftKeepsTheMoneyAndReferenceLimits(): void
    {
        foreach ([0, 1_000_000_000_001] as $amount) {
            try {
                $this->online($amount);
                self::fail("$amount minor units must be rejected");
            } catch (GivingException $error) {
                self::assertSame(ErrorKind::Invalid, $error->kind());
            }
        }
        $this->expectInvalid('Reference is required and must be at most 200 characters.');
        $this->online(reference: str_repeat('r', 201));
    }

    public function testARefundIsBoundedByTheUnrefundedAmountAndTheReceivedDate(): void
    {
        $gift = $this->online();
        $now = new DateTimeImmutable('2026-02-01T10:00:00Z');

        $refund = $gift->refundOnline(self::REFUND, 2500, 'payfast:live:rf_1', CalendarDate::of(2026, 1, 10), 7500, self::ACTOR, $now);
        self::assertSame([self::REFUND, self::GIFT, self::FUND, 2500, 'payfast:live:rf_1', '2026-01-10', self::ACTOR], [
            $refund->id, $refund->giftId, $refund->fundId, $refund->amountMinor, $refund->reference, $refund->refundedOn->iso(), $refund->createdBy,
        ]);
        self::assertSame(10000, $gift->refundOnline(self::REFUND, 10000, 'r', CalendarDate::of(2026, 2, 1), 0, self::ACTOR, $now)->amountMinor);

        $rejected = [
            'zero' => [$gift, 0, CalendarDate::of(2026, 2, 1), 0],
            'over the unrefunded amount' => [$gift, 2501, CalendarDate::of(2026, 2, 1), 7500],
            'before the received date' => [$gift, 100, CalendarDate::of(2026, 1, 9), 0],
            'a manual gift' => [$this->gift([new Allocation(self::FUND, 10000)]), 100, CalendarDate::of(2026, 2, 1), 0],
        ];
        foreach ($rejected as $case => [$target, $amount, $date, $refunded]) {
            try {
                $target->refundOnline(self::REFUND, $amount, 'r', $date, $refunded, self::ACTOR, $now);
                self::fail("$case must be rejected");
            } catch (GivingException $error) {
                self::assertSame([ErrorKind::Conflict, 'Refund must match the unrefunded online gift and its dates.'], [$error->kind(), $error->getMessage()], $case);
            }
        }
    }

    public function testAllocationsRenderLikeTheJavaRecord(): void
    {
        self::assertSame('Allocation[fundId='.self::FUND.', amountMinor=5000]', new Allocation(self::FUND, 5000)->javaString());
    }
}
