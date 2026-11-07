<?php

declare(strict_types=1);

namespace App\Giving\Presentation;

use App\Giving\Application\Contract\LedgerReport;
use App\Giving\Domain\JavaString;

/**
 * The ledger as CSV (Java GivingCsv): UTF-8 with a byte-order mark, CRLF rows, every text cell quoted, and a cell
 * whose first non-whitespace character is `= + - @` prefixed with an apostrophe so spreadsheets never run it as a
 * formula. Signed amounts stay unquoted numbers.
 */
final class GivingCsv
{
    private const string HEADER = "\u{FEFF}date,kind,gift_id,donor,fund,currency,amount_minor,method,reference,reason\r\n";

    private function __construct()
    {
    }

    public static function render(LedgerReport $report): string
    {
        $csv = self::HEADER;
        foreach ($report->lines as $line) {
            $csv .= implode(',', [
                self::text($line->date->iso()),
                self::text($line->kind),
                self::text($line->giftId),
                self::text($line->donorName),
                self::text($line->fundName),
                self::text($line->currency),
                (string) $line->amountMinor,
                self::text($line->method),
                self::text($line->reference),
                self::text($line->reason),
            ])."\r\n";
        }

        return $csv;
    }

    private static function text(?string $value): string
    {
        $safe = $value ?? '';
        $leading = JavaString::stripLeading($safe);
        if ('' !== $leading && str_contains('=+-@', $leading[0])) {
            $safe = "'".$safe;
        }

        return '"'.str_replace('"', '""', $safe).'"';
    }
}
