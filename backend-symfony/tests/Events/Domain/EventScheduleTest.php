<?php

declare(strict_types=1);

namespace App\Tests\Events\Domain;

use App\Events\Domain\EventException;
use App\Events\Domain\EventSchedule;
use App\Events\Domain\EventSeries;
use App\Events\Domain\TimeOfDay;
use App\Shared\Domain\CalendarDate;
use PHPUnit\Framework\TestCase;

final class EventScheduleTest extends TestCase
{
    private const string ID = '00000000-0000-4000-8000-000000000001';

    private function window(string $date, string $start, string $end, int $offset, string $zone): string
    {
        $window = EventSchedule::window(CalendarDate::parse($date), TimeOfDay::parse($start), TimeOfDay::parse($end), $offset, $zone);

        return $window->startsAt->format('Y-m-d\TH:i\Z').'/'.$window->endsAt->format('Y-m-d\TH:i\Z');
    }

    private function series(string $frequency, string $first, int $count, int $interval = 1, string $zone = 'UTC'): EventSeries
    {
        return EventSeries::create(self::ID, self::ID, $zone, $frequency, $interval, $count, CalendarDate::parse($first), TimeOfDay::parse('10:00'), TimeOfDay::parse('11:00'), 0, self::ID);
    }

    /** @return list<string> */
    private function dates(EventSeries $series): array
    {
        return array_map(static fn ($w): string => $w->originDate->iso(), EventSchedule::preview($series));
    }

    public function testLocalTimesConvertWithTheZoneOffsetInEffect(): void
    {
        self::assertSame('2026-07-01T09:00Z/2026-07-01T10:30Z', $this->window('2026-07-01', '10:00', '11:30', 0, 'Europe/London'));
        self::assertSame('2026-01-01T10:00Z/2026-01-01T11:30Z', $this->window('2026-01-01', '10:00', '11:30', 0, 'Europe/London'));
        self::assertSame('2026-07-01T08:00Z/2026-07-02T09:00Z', $this->window('2026-07-01', '10:00', '11:00', 1, '+02:00'));
    }

    public function testATimeInTheSpringForwardGapIsRejected(): void
    {
        $this->expectException(EventException::class);
        $this->expectExceptionMessage('ambiguous or does not exist');

        $this->window('2026-03-29', '01:30', '03:00', 0, 'Europe/London');
    }

    public function testATimeInTheAutumnOverlapIsRejected(): void
    {
        $this->expectException(EventException::class);
        $this->expectExceptionMessage('ambiguous or does not exist');

        $this->window('2026-10-25', '01:30', '03:00', 0, 'Europe/London');
    }

    public function testAnEndInAGapOrOverlapIsRejectedToo(): void
    {
        $this->expectException(EventException::class);

        $this->window('2026-03-08', '00:30', '02:30', 0, 'America/New_York');
    }

    public function testTimesAroundATransitionButNotInsideItAreAccepted(): void
    {
        self::assertSame('2026-03-29T00:30Z/2026-03-29T02:00Z', $this->window('2026-03-29', '00:30', '03:00', 0, 'Europe/London'));
        self::assertSame('2026-10-25T02:30Z/2026-10-25T03:30Z', $this->window('2026-10-25', '02:30', '03:30', 0, 'Europe/London'));
    }

    public function testTheWindowMustBePositiveAndAtMostSevenDays(): void
    {
        foreach ([['11:00', '10:00', 0], ['10:00', '10:00', 0], ['10:00', '11:00', 8]] as [$start, $end, $offset]) {
            try {
                $this->window('2026-07-01', $start, $end, $offset, 'UTC');
                self::fail("$start-$end+$offset should be rejected");
            } catch (EventException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function testYearsOutsideTheSupportedRangeAreRejected(): void
    {
        $this->expectException(EventException::class);

        $this->window('1899-12-31', '10:00', '11:00', 0, 'UTC');
    }

    public function testRecurrenceStepsDaysWeeksAndMonths(): void
    {
        self::assertSame(['2026-07-01'], $this->dates($this->series('ONCE', '2026-07-01', 1)));
        self::assertSame(['2026-07-01', '2026-07-03', '2026-07-05'], $this->dates($this->series('DAILY', '2026-07-01', 3, 2)));
        self::assertSame(['2026-07-01', '2026-07-15'], $this->dates($this->series('WEEKLY', '2026-07-01', 2, 2)));
        self::assertSame(['2026-01-28', '2026-02-28', '2026-03-28'], $this->dates($this->series('MONTHLY', '2026-01-28', 3)));
    }

    public function testAMonthlyDateMissingFromAShortMonthIsRejectedNotOverflowed(): void
    {
        $this->expectException(EventException::class);
        $this->expectExceptionMessage('A monthly date is missing');

        $this->dates($this->series('MONTHLY', '2026-01-31', 2));
    }

    public function testAPreviewMustFitWithinTwoYears(): void
    {
        self::assertCount(104, EventSchedule::preview($this->series('WEEKLY', '2026-01-01', 104)));

        $this->expectException(EventException::class);
        $this->expectExceptionMessage('two years');

        $this->dates($this->series('WEEKLY', '2026-01-01', 4, 52));
    }

    public function testSeriesRulesAreEnforced(): void
    {
        foreach ([
            fn () => $this->series('ONCE', '2026-07-01', 2),
            fn () => $this->series('YEARLY', '2026-07-01', 1),
            fn () => $this->series('DAILY', '2026-07-01', 105),
            fn () => $this->series('DAILY', '2026-07-01', 1, 53),
            fn () => $this->series('DAILY', '2026-07-01', 1, 1, 'Nowhere/City'),
        ] as $build) {
            try {
                $build();
                self::fail('Expected a rejection.');
            } catch (EventException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function testTimeOfDayRendersLikeJavaAndIso(): void
    {
        self::assertSame('10:00', TimeOfDay::parse('10:00')->javaString());
        self::assertSame('10:00:00', TimeOfDay::parse('10:00')->iso());
        self::assertSame('10:00:05', TimeOfDay::parse('10:00:05')->javaString());
        self::assertSame('10:00:00.500', TimeOfDay::parse('10:00:00.5')->javaString());
        self::assertSame('10:00:00.5', TimeOfDay::parse('10:00:00.5')->iso());
        self::assertSame('10:00:00.123456', TimeOfDay::parse('10:00:00.123456789')->javaString());
    }
}
