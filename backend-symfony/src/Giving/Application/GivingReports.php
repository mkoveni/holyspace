<?php

declare(strict_types=1);

namespace App\Giving\Application;

use App\Giving\Application\Contract\GiftFilter;
use App\Giving\Application\Contract\GivingLedgerReader;
use App\Giving\Application\Contract\GivingTotal;
use App\Giving\Application\Contract\LedgerLine;
use App\Giving\Application\Contract\LedgerReport;
use App\People\Application\Contract\PersonDirectory;
use Psr\Clock\ClockInterface;

/**
 * Builds statements and ledger exports (Java GivingReports): at most 5,000 lines, totals per currency computed from
 * those same lines, so a report and its totals can never disagree.
 */
final readonly class GivingReports
{
    public const int MAX_LINES = 5000;

    public function __construct(
        private GivingLedgerReader $ledger,
        private PersonDirectory $people,
        private ClockInterface $clock,
    ) {
    }

    public function report(GiftFilter $filter): LedgerReport
    {
        GivingFilters::validate($filter);
        $lines = $this->ledger->lines($filter, self::MAX_LINES);
        $donorId = $filter->donorId;
        $name = null === $donorId ? 'All donors' : ($this->people->get($donorId)->name ?? 'Person unavailable');
        \assert(null !== $filter->from && null !== $filter->to);

        return new LedgerReport($donorId, $name, $filter->from, $filter->to, $this->clock->now(), $lines, self::totals($lines));
    }

    /**
     * Gross is the sum of positive lines, reversed the negated sum of negative lines. Integer arithmetic is exact here:
     * 5,000 lines of at most 10^12 minor units stay far below PHP_INT_MAX.
     *
     * @param list<LedgerLine> $lines
     *
     * @return list<GivingTotal>
     */
    private static function totals(array $lines): array
    {
        /** @var array<string, array{int, int}> $sums */
        $sums = [];
        foreach ($lines as $line) {
            [$gross, $reversed] = $sums[$line->currency] ?? [0, 0];
            if ($line->amountMinor > 0) {
                $gross += $line->amountMinor;
            } elseif ($line->amountMinor < 0) {
                $reversed -= $line->amountMinor;
            }
            $sums[$line->currency] = [$gross, $reversed];
        }
        ksort($sums, \SORT_STRING);
        $totals = [];
        foreach ($sums as $currency => [$gross, $reversed]) {
            $totals[] = new GivingTotal((string) $currency, (string) $gross, (string) $reversed, (string) ($gross - $reversed));
        }

        return $totals;
    }
}
