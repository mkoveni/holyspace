<?php

declare(strict_types=1);

namespace App\Tests\Events;

use App\Events\Domain\Model\ChurchEvent;
use App\Events\Domain\ValueObject\EventTime;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class EventScheduleTest extends TestCase
{
    public function testWeeklySchedulePreservesLocalTimeAcrossDaylightSaving(): void
    {
        $zone = 'Europe/London';
        $event = ChurchEvent::create('Worship', 'service', null, $zone, EventTime::local('2026-03-22T09:00', $zone), EventTime::local('2026-03-22T11:00', $zone), 'Church', 'weekly', 3);
        self::assertSame(['2026-03-22T09:00:00+00:00', '2026-03-29T09:00:00+01:00', '2026-04-05T09:00:00+01:00'], array_map(fn ($o) => $o->startsAt()->format(DATE_ATOM), $event->occurrences()));
    }

    public function testInvalidLocalTimeInDaylightSavingGapIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        EventTime::local('2026-03-29T01:30', 'Europe/London');
    }

    public function testWeeklyRecurrenceInDaylightSavingGapIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $zone = 'Europe/London';
        ChurchEvent::create('Worship', 'service', null, $zone, EventTime::local('2026-03-22T01:30', $zone), EventTime::local('2026-03-22T03:00', $zone), 'Church', 'weekly', 2);
    }
}
