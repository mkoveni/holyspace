<?php

declare(strict_types=1);

namespace App\Tests\ChildrenMinistry\Sessions\Domain;

use App\ChildrenMinistry\Sessions\Domain\RecurrenceSlot;
use App\ChildrenMinistry\Sessions\Domain\WeeklyRecurrence;
use App\ChildrenMinistry\Shared\Domain\ChildrenException;
use App\Shared\Domain\CalendarDate;
use DateTimeZone;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class WeeklyRecurrenceTest extends TestCase
{
    public function testWeeklyWallTimesRemainStableAcrossDaylightSaving(): void
    {
        $rule = new WeeklyRecurrence(CalendarDate::parse('2026-03-22'), '09:00', '10:00', 1, 3);

        self::assertSame(
            ['2026-03-22T09:00:00Z', '2026-03-29T08:00:00Z', '2026-04-05T08:00:00Z'],
            array_map(static fn (RecurrenceSlot $slot): string => $slot->startsAt->format('Y-m-d\TH:i:s\Z'), $rule->slots(new DateTimeZone('Europe/London'))),
        );
    }

    /** @return iterable<string, array{string}> */
    public static function skippedWallTimes(): iterable
    {
        yield 'spring forward gap' => ['2026-03-29'];
        yield 'autumn overlap' => ['2026-10-25'];
    }

    #[DataProvider('skippedWallTimes')]
    public function testAmbiguousAndMissingLocalTimesRequireAnExplicitDifferentTime(string $date): void
    {
        $rule = new WeeklyRecurrence(CalendarDate::parse($date), '01:30', '03:00', 1, 1);

        $this->expectException(ChildrenException::class);
        $this->expectExceptionMessage('ambiguous or unavailable');
        $rule->slots(new DateTimeZone('Europe/London'));
    }

    public function testExcludedDatesMarkTheirSlotsAndAreSortedAndUnique(): void
    {
        $first = CalendarDate::parse('2026-11-01');
        $rule = new WeeklyRecurrence($first, '09:00', '10:00', 2, 3, [$first->plusDays(28), $first->plusDays(28)]);

        self::assertSame([false, false, true], array_map(static fn (RecurrenceSlot $slot): bool => $slot->excluded, $rule->slots(new DateTimeZone('Africa/Johannesburg'))));
        self::assertCount(1, $rule->excludedDates);
        self::assertSame('2026-11-01', $rule->slots(new DateTimeZone('Africa/Johannesburg'))[0]->date->iso());
        self::assertSame('2026-11-15', $rule->slots(new DateTimeZone('Africa/Johannesburg'))[1]->date->iso());
    }

    /** @return iterable<string, array{callable(): WeeklyRecurrence}> */
    public static function invalidPatterns(): iterable
    {
        $first = CalendarDate::parse('2026-11-01');
        yield 'longer than 366 days' => [static fn (): WeeklyRecurrence => new WeeklyRecurrence($first, '12:00', '13:00', 4, 52)];
        yield 'exclusion outside the pattern' => [static fn (): WeeklyRecurrence => new WeeklyRecurrence($first, '12:00', '13:00', 1, 2, [$first->plusDays(1)])];
        yield 'end before start' => [static fn (): WeeklyRecurrence => new WeeklyRecurrence($first, '12:00', '11:00', 1, 2)];
        yield 'end equals start' => [static fn (): WeeklyRecurrence => new WeeklyRecurrence($first, '12:00', '12:00', 1, 2)];
        yield 'every date skipped' => [static fn (): WeeklyRecurrence => new WeeklyRecurrence($first, '12:00', '13:00', 1, 1, [$first])];
        yield 'too many occurrences' => [static fn (): WeeklyRecurrence => new WeeklyRecurrence($first, '12:00', '13:00', 1, 53)];
        yield 'no occurrences' => [static fn (): WeeklyRecurrence => new WeeklyRecurrence($first, '12:00', '13:00', 1, 0)];
        yield 'interval too long' => [static fn (): WeeklyRecurrence => new WeeklyRecurrence($first, '12:00', '13:00', 5, 2)];
        yield 'seconds are not minutes' => [static fn (): WeeklyRecurrence => new WeeklyRecurrence($first, '12:00:30', '13:00', 1, 2)];
        yield 'not a time' => [static fn (): WeeklyRecurrence => new WeeklyRecurrence($first, '25:00', '26:00', 1, 2)];
    }

    /** @param callable(): WeeklyRecurrence $make */
    #[DataProvider('invalidPatterns')]
    public function testInvalidPatternsAreRejected(callable $make): void
    {
        $this->expectException(ChildrenException::class);
        $make();
    }

    public function testTheLongestAllowedPatternIsAccepted(): void
    {
        $rule = new WeeklyRecurrence(CalendarDate::parse('2026-11-01'), '12:00:00', '13:00:00', 1, 52);

        self::assertCount(52, $rule->slots(new DateTimeZone('UTC')));
        self::assertSame('12:00:00', $rule->startsAt);
    }
}
