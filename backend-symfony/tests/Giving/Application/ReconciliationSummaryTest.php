<?php

declare(strict_types=1);

namespace App\Tests\Giving\Application;

use App\Giving\Application\Contract\ReconciliationBatchView;
use App\Giving\Application\Contract\ReconciliationItem;
use App\Giving\Application\Contract\ReconciliationReviewView;
use App\Giving\Application\Contract\ReconciliationSummary;
use App\Shared\Domain\CalendarDate;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

/** Java's needsReview rule from JooqReconciliationStore::summary. */
final class ReconciliationSummaryTest extends TestCase
{
    private static function batch(int $expected, int $actual = 12500): ReconciliationBatchView
    {
        return new ReconciliationBatchView('b', 'BANK-001', $expected, $actual, 'ZAR', CalendarDate::of(2026, 2, 1), '', 'a', new DateTimeImmutable(), ['g1', 'g2']);
    }

    /** @return list<ReconciliationItem> */
    private static function items(bool $reversed = false, int $refunded = 0): array
    {
        return [new ReconciliationItem('g1', 'Anonymous', CalendarDate::of(2026, 1, 5), 10000, $reversed, $refunded), new ReconciliationItem('g2', 'Anonymous', CalendarDate::of(2026, 1, 6), 2500, false, 300)];
    }

    private static function review(string $outcome, int $reversed, int $version = 2): ReconciliationReviewView
    {
        return new ReconciliationReviewView('r', 'b', $version, $outcome, 'Note', $reversed, 'a', new DateTimeImmutable());
    }

    public function testReversedCountsWholeReversedGiftsAndOtherwiseRefunds(): void
    {
        $summary = ReconciliationSummary::of(self::batch(12500), self::items(true, 999), null);

        self::assertSame([10300, 2200, 0, true], [$summary->reversedMinor, $summary->currentNetMinor, $summary->version, $summary->needsReview]);
    }

    public function testNeedsReviewBeforeAndAfterAReview(): void
    {
        $clean = [new ReconciliationItem('g1', 'Anonymous', CalendarDate::of(2026, 1, 5), 12500, false, 0)];
        self::assertFalse(ReconciliationSummary::of(self::batch(12500), $clean, null)->needsReview);
        self::assertTrue(ReconciliationSummary::of(self::batch(13000), $clean, null)->needsReview, 'a mismatch');
        self::assertTrue(ReconciliationSummary::of(self::batch(12500), self::items(), null)->needsReview, 'a refund since creation');

        $explained = ReconciliationSummary::of(self::batch(13000), self::items(), self::review('EXPLAINED', 300));
        self::assertSame([false, 2], [$explained->needsReview, $explained->version]);
        self::assertTrue(ReconciliationSummary::of(self::batch(13000), self::items(true), self::review('EXPLAINED', 300))->needsReview, 'a later reversal reopens it');
        self::assertTrue(ReconciliationSummary::of(self::batch(13000), self::items(), self::review('FOLLOW_UP', 300))->needsReview);
    }
}
