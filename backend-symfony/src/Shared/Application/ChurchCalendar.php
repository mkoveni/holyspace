<?php

declare(strict_types=1);

namespace App\Shared\Application;

use App\Shared\Domain\CalendarDate;
use DateTimeZone;
use Psr\Clock\ClockInterface;

/** "Today" as the church sees it: ages and future-date checks use the church's time zone, not UTC. */
final readonly class ChurchCalendar
{
    private DateTimeZone $zone;

    public function __construct(private ClockInterface $clock, private string $timeZone)
    {
        $this->zone = new DateTimeZone($timeZone);
    }

    public function today(): CalendarDate
    {
        return CalendarDate::fromInstant($this->clock->now(), $this->zone);
    }

    public function timeZone(): string
    {
        return $this->timeZone;
    }
}
