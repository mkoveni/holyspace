<?php

declare(strict_types=1);

namespace App\Tests\Shared\Domain;

use App\Shared\Domain\CalendarDate;
use App\Shared\Domain\InvalidInputException;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CalendarDateTest extends TestCase
{
    public function testParsesAndFormatsIsoDates(): void
    {
        self::assertSame('2026-02-28', CalendarDate::parse('2026-02-28')->iso());
    }

    /** @return iterable<string, array{string}> */
    public static function invalid(): iterable
    {
        yield 'not a date' => ['yesterday'];
        yield 'impossible day' => ['2026-02-30'];
        yield 'impossible month' => ['2026-13-01'];
        yield 'wrong separator' => ['2026/02/28'];
        yield 'with time' => ['2026-02-28T10:00:00'];
        yield 'empty' => [''];
    }

    #[DataProvider('invalid')]
    public function testRejectsInvalidDates(string $value): void
    {
        $this->expectException(InvalidInputException::class);

        CalendarDate::parse($value);
    }

    public function testComparesByCalendarDay(): void
    {
        $day = CalendarDate::parse('2026-05-10');

        self::assertTrue($day->isBefore(CalendarDate::parse('2026-05-11')));
        self::assertTrue($day->isAfter(CalendarDate::parse('2025-12-31')));
        self::assertFalse($day->isBefore(CalendarDate::parse('2026-05-10')));
        self::assertFalse($day->isAfter(CalendarDate::parse('2026-05-10')));
    }

    public function testLocalDayDependsOnTheZone(): void
    {
        $instant = new DateTimeImmutable('2026-05-10T23:30:00Z');

        self::assertSame('2026-05-10', CalendarDate::fromInstant($instant, new DateTimeZone('UTC'))->iso());
        self::assertSame('2026-05-11', CalendarDate::fromInstant($instant, new DateTimeZone('Africa/Johannesburg'))->iso());
    }

    public function testMonthsClampToTheLastDayOfAShorterMonth(): void
    {
        self::assertSame('2026-02-28', CalendarDate::parse('2026-01-31')->plusMonths(1)->iso());
        self::assertSame('2024-02-29', CalendarDate::parse('2024-01-31')->plusMonths(1)->iso());
        self::assertSame('2027-01-15', CalendarDate::parse('2026-10-15')->plusMonths(3)->iso());
        self::assertSame('2025-12-10', CalendarDate::parse('2026-01-10')->plusMonths(-1)->iso());
        self::assertSame('2026-02-28', CalendarDate::parse('2008-02-29')->plusYears(18)->iso());
    }

    public function testLeapDayFallsBackToFebruary28(): void
    {
        self::assertSame('2025-02-28', CalendarDate::parse('2024-02-29')->minusYears(-1)->iso());
        self::assertSame('2028-02-29', CalendarDate::parse('2024-02-29')->minusYears(-4)->iso());
    }

    public function testYearAndSignedDaysBetweenDates(): void
    {
        self::assertSame(1900, CalendarDate::parse('1900-01-01')->year());
        self::assertSame(0, CalendarDate::parse('2026-03-29')->daysUntil(CalendarDate::parse('2026-03-29')));
        self::assertSame(366, CalendarDate::parse('2024-01-01')->daysUntil(CalendarDate::parse('2025-01-01')));
        self::assertSame(-1, CalendarDate::parse('2026-01-01')->daysUntil(CalendarDate::parse('2025-12-31')));
        self::assertSame(3653, CalendarDate::parse('2016-01-01')->daysUntil(CalendarDate::parse('2026-01-01')));
    }
}
