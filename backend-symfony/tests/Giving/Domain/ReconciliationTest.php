<?php

declare(strict_types=1);

namespace App\Tests\Giving\Domain;

use App\Giving\Domain\GivingException;
use App\Giving\Domain\Reconciliation;
use App\Giving\Domain\ReconciliationReview;
use App\Shared\Domain\CalendarDate;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class ReconciliationTest extends TestCase
{
    private const string ACTOR = '99999999-9999-4999-8999-999999999999';
    private const string GIFT = '00000000-0000-4000-8000-0000000000aa';

    /** @param list<string>|null $gifts */
    private static function batch(int $expected = 10000, int $actual = 10000, ?string $reason = null, ?array $gifts = null, ?string $reference = 'BANK-001', ?string $currency = 'ZAR'): Reconciliation
    {
        return Reconciliation::create('00000000-0000-4000-8000-0000000000b1', $reference, $expected, $actual, $currency, CalendarDate::of(2026, 2, 1), $reason, self::ACTOR, new DateTimeImmutable('2026-02-01T08:00:00Z'), $gifts ?? [self::GIFT]);
    }

    private static function invalid(callable $create): string
    {
        try {
            $create();
        } catch (GivingException $error) {
            return $error->getMessage();
        }
        self::fail('the batch was accepted');
    }

    public function testAMatchingBatchNeedsNoExplanationAndTrimsTheText(): void
    {
        $batch = self::batch(reason: '  ', reference: ' BANK-001 ');

        self::assertSame(['BANK-001', '', 'ZAR', [self::GIFT]], [$batch->reference, $batch->reason, $batch->currency, $batch->giftIds]);
        self::assertSame('Verified', self::batch(reason: ' Verified ')->reason);
    }

    public function testAmountsAndGiftsAreBoundedAtBothEdges(): void
    {
        self::assertSame(1, self::batch(1, 1)->expectedMinor);
        self::assertSame(Reconciliation::MAX_MINOR, self::batch(Reconciliation::MAX_MINOR, Reconciliation::MAX_MINOR)->actualMinor);
        $hundred = array_map(static fn (int $i): string => \sprintf('00000000-0000-4000-8000-%012d', $i), range(1, 100));
        self::assertCount(100, self::batch(gifts: $hundred)->giftIds);

        $message = 'Select 1–100 distinct gifts and a positive expected batch amount.';
        foreach ([
            static fn () => self::batch(0, 1, 'x'),
            static fn () => self::batch(Reconciliation::MAX_MINOR + 1, 1, 'x'),
            static fn () => self::batch(1, 0, 'x'),
            static fn () => self::batch(gifts: []),
            static fn () => self::batch(gifts: [...$hundred, self::GIFT]),
            static fn () => self::batch(gifts: [self::GIFT, self::GIFT]),
        ] as $create) {
            self::assertSame($message, self::invalid($create));
        }
    }

    public function testADiscrepancyNeedsAnExplanation(): void
    {
        self::assertSame('Discrepancy explanation is required and must be at most 1000 characters.', self::invalid(static fn () => self::batch(11000, 10000)));
        self::assertSame('Discrepancy explanation is required and must be at most 1000 characters.', self::invalid(static fn () => self::batch(11000, 10000, str_repeat('r', 1001))));
        self::assertSame('Bank charge', self::batch(11000, 10000, 'Bank charge')->reason);
        self::assertSame('The explanation must be at most 1000 characters.', self::invalid(static fn () => self::batch(reason: str_repeat('r', 1001))));
        self::assertSame(1000, \strlen(self::batch(reason: str_repeat('r', 1000))->reason));
    }

    public function testReferenceAndCurrencyAreChecked(): void
    {
        self::assertSame('Bank or cash batch reference is required and must be at most 200 characters.', self::invalid(static fn () => self::batch(reference: ' ')));
        self::assertSame('Choose ZAR, USD, EUR or GBP. All supported currencies use two decimal places.', self::invalid(static fn () => self::batch(currency: 'JPY')));
    }

    public function testAReviewIsTheNextVersionWithAnOutcomeAndEvidence(): void
    {
        $review = self::batch()->review('00000000-0000-4000-8000-0000000000c1', 2, ReconciliationReview::EXPLAINED, ' Bank correction ', 500, self::ACTOR, new DateTimeImmutable('2026-02-02T08:00:00Z'));

        self::assertSame(['00000000-0000-4000-8000-0000000000b1', 3, 'EXPLAINED', 'Bank correction', 500], [$review->batchId, $review->version, $review->outcome, $review->note, $review->reversedMinor]);
        foreach ([[null, 'Note', 'Choose follow-up or explained.'], ['DONE', 'Note', 'Choose follow-up or explained.'], ['FOLLOW_UP', ' ', 'Review evidence is required and must be at most 1000 characters.']] as [$outcome, $note, $message]) {
            self::assertSame($message, self::invalid(static fn () => self::batch()->review('r', 0, $outcome, $note, 0, self::ACTOR, new DateTimeImmutable())));
        }
    }
}
