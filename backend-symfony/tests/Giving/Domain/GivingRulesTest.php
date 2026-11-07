<?php

declare(strict_types=1);

namespace App\Tests\Giving\Domain;

use App\Giving\Domain\GivingException;
use App\Giving\Domain\GivingRules;
use App\Giving\Domain\JavaString;
use App\Shared\Domain\CalendarDate;
use App\Shared\Domain\ErrorKind;
use PHPUnit\Framework\TestCase;

final class GivingRulesTest extends TestCase
{
    public function testTextIsTrimmedAndBoundedByItsJavaLength(): void
    {
        self::assertSame('Roof', GivingRules::text("  Roof\t", 4, 'Name'));
        self::assertSame(str_repeat('a', 200), GivingRules::text(str_repeat('a', 200), 200, 'Reference'));
    }

    public function testTextRejectsMissingBlankAndOverlongValuesWithTheLabel(): void
    {
        foreach ([null, '', "  \t\n", "\u{2003}", str_repeat('a', 5), '😀😀😀'] as $value) {
            try {
                GivingRules::text($value, 4, 'Fund name');
                self::fail('accepted '.var_export($value, true));
            } catch (GivingException $e) {
                self::assertSame(ErrorKind::Invalid, $e->kind());
                self::assertSame('Fund name is required and must be at most 4 characters.', $e->getMessage());
            }
        }
    }

    public function testJavaStringSemantics(): void
    {
        self::assertSame(2, JavaString::length('😀'));
        self::assertSame(1, JavaString::length('é'));
        self::assertSame("\u{00A0}x\u{00A0}", JavaString::trim(" \u{00A0}x\u{00A0}\n"));
        self::assertFalse(JavaString::isBlank("\u{00A0}"));
        self::assertTrue(JavaString::isBlank("\u{3000} \t"));
        self::assertTrue(JavaString::isBlank(''));
    }

    public function testVersionMismatchIsAConflict(): void
    {
        GivingRules::version(3, 3);

        $this->expectExceptionObject(GivingException::conflict('This record changed. Refresh before trying again.'));
        GivingRules::version(3, 2);
    }

    public function testDatesRunFrom1900ThroughToday(): void
    {
        $today = CalendarDate::parse('2026-11-07');
        GivingRules::date(CalendarDate::parse('1900-01-01'), $today);
        GivingRules::date($today, $today);

        foreach ([null, CalendarDate::parse('1899-12-31'), CalendarDate::parse('2026-11-08')] as $date) {
            try {
                GivingRules::date($date, $today);
                self::fail('accepted '.($date?->iso() ?? 'null'));
            } catch (GivingException $e) {
                self::assertSame('Choose a date from 1900 through today.', $e->getMessage());
            }
        }
    }

    public function testPagesAreBounded(): void
    {
        GivingRules::page(1, 0);
        GivingRules::page(100, 100_000);

        foreach ([[0, 0], [101, 0], [1, -1], [1, 100_001]] as [$limit, $offset]) {
            try {
                GivingRules::page($limit, $offset);
                self::fail("accepted $limit/$offset");
            } catch (GivingException $e) {
                self::assertSame('Use a page size of 1–100 and an offset of 0–100000.', $e->getMessage());
            }
        }
    }

    public function testFailureKindsAndFixedMessages(): void
    {
        self::assertSame(ErrorKind::NotFound, GivingException::missing()->kind());
        self::assertSame('The Giving record was not found.', GivingException::missing()->getMessage());
        self::assertSame(ErrorKind::Forbidden, GivingException::forbidden()->kind());
        self::assertSame('Verified donor access is required.', GivingException::forbidden()->getMessage());
    }
}
