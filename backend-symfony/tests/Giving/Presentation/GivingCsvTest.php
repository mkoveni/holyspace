<?php

declare(strict_types=1);

namespace App\Tests\Giving\Presentation;

use App\Giving\Application\Contract\LedgerLine;
use App\Giving\Application\Contract\LedgerReport;
use App\Giving\Presentation\GivingCsv;
use App\Shared\Domain\CalendarDate;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class GivingCsvTest extends TestCase
{
    private const string GIFT = '00000000-0000-4000-8000-0000000000aa';
    private const string FUND = '00000000-0000-4000-8000-000000000001';

    private static function render(LedgerLine ...$lines): string
    {
        $date = CalendarDate::of(2026, 1, 1);

        return GivingCsv::render(new LedgerReport(null, '', $date, $date, new DateTimeImmutable(), array_values($lines), []));
    }

    private static function line(string $donor = 'Donor', ?string $reference = 'REF', string $reason = '', int $amount = 100): LedgerLine
    {
        return new LedgerLine(self::GIFT, 'GIFT', CalendarDate::of(2026, 1, 1), null, $donor, self::FUND, 'Fund', 'ZAR', $amount, 'BANK', $reference, $reason);
    }

    public function testEscapesSpreadsheetFormulasAndKeepsSignedAmountsNumeric(): void
    {
        $csv = self::render(self::line('=HYPERLINK("unsafe")', "\t+formula", "Quote \" and newline\n", -12345));

        foreach (["'=HYPERLINK", '-12345,', "'\t+formula", 'Quote "" and newline'] as $expected) {
            self::assertStringContainsString($expected, $csv);
        }
    }

    public function testHeaderByteOrderMarkQuotingAndCrlfMatchJava(): void
    {
        self::assertSame(
            "\u{FEFF}date,kind,gift_id,donor,fund,currency,amount_minor,method,reference,reason\r\n"
            .'"2026-01-01","GIFT","'.self::GIFT.'","Donor","Fund","ZAR",100,"BANK","",""'."\r\n",
            self::render(self::line(reference: null)),
        );
        self::assertSame("\u{FEFF}date,kind,gift_id,donor,fund,currency,amount_minor,method,reference,reason\r\n", self::render());
    }

    public function testTheFormulaGuardLooksPastJavaWhitespaceOnly(): void
    {
        foreach (['-1' => "\"'-1\"", '@x' => "\"'@x\"", " \u{2003}=x" => "\"' \u{2003}=x\"", "\u{A0}=x" => "\"\u{A0}=x\"", 'a=b' => '"a=b"', '' => '""'] as $donor => $cell) {
            self::assertStringContainsString(','.$cell.',"Fund"', self::render(self::line((string) $donor)), (string) $donor);
        }
    }
}
