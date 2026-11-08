<?php

declare(strict_types=1);

namespace App\Tests\Events\Application;

use App\Events\Application\Contract\CalendarFailure;
use App\Events\Application\Contract\CommunitySchedule;
use PHPUnit\Framework\TestCase;

final class CommunityScheduleTest extends TestCase
{
    public function testItPrintsJavasRecordTextForRequestHashes(): void
    {
        $schedule = CommunitySchedule::of('0000000A-0000-4000-8000-0000000000A1', 'UTC', 'WEEKLY', 1, 3, '2026-07-05', '10:00:00', '11:30:15', 0);

        self::assertSame(
            'Schedule[venueId=0000000a-0000-4000-8000-0000000000a1, timeZone=UTC, frequency=WEEKLY, interval=1, count=3, firstDate=2026-07-05, startsAt=10:00, endsAt=11:30:15, endDayOffset=0]',
            $schedule->javaString(),
        );
    }

    public function testMalformedDatesAndTimesAreCalendarFailuresWithStatus400(): void
    {
        foreach ([['2026-02-30', '10:00'], ['2026/07/05', '10:00'], ['2026-07-05', '24:00']] as [$date, $time]) {
            try {
                CommunitySchedule::of('v', 'UTC', 'ONCE', 1, 1, $date, $time, '11:00', 0);
                self::fail("$date $time was accepted");
            } catch (CalendarFailure $e) {
                self::assertSame(400, $e->status);
            }
        }
    }
}
