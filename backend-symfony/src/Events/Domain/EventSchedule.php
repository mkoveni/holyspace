<?php

declare(strict_types=1);

namespace App\Events\Domain;

use App\Shared\Domain\CalendarDate;
use DateTimeImmutable;
use DateTimeZone;

/**
 * Turns local dates and times into UTC windows. Unlike DateTimeImmutable, which silently shifts a time in a
 * daylight-saving gap and picks the first of two offsets in an overlap, any local time that is not
 * exactly one instant is rejected, as the Java application does.
 */
final class EventSchedule
{
    private const int DAY = 86_400;

    private function __construct()
    {
    }

    public static function window(CalendarDate $date, TimeOfDay $start, TimeOfDay $end, int $offset, string $timeZone): ScheduleWindow
    {
        $year = (int) substr($date->iso(), 0, 4);
        if ($year < 1900 || $year > 2200 || $offset < 0 || $offset > 7) {
            throw EventException::invalid('Choose a date, times and end day offset of 0–7.');
        }
        $zone = EventRules::zone($timeZone);
        $starts = self::instant($date, $start, $zone);
        $ends = self::instant($date->plusDays($offset), $end, $zone);
        EventRules::period($starts, $ends);

        return new ScheduleWindow($date, $starts, $ends);
    }

    /** @return list<ScheduleWindow> */
    public static function preview(EventSeries $series): array
    {
        $limit = $series->firstDate->plusYears(2);
        $windows = [];
        for ($index = 0; $index < $series->count; ++$index) {
            $step = $index * $series->interval;
            $date = match ($series->frequency) {
                'ONCE' => $series->firstDate,
                'DAILY' => $series->firstDate->plusDays($step),
                'WEEKLY' => $series->firstDate->plusDays(7 * $step),
                'MONTHLY' => $series->firstDate->plusMonths($step),
                default => throw EventException::invalid('Choose a recurrence.'),
            };
            if ($date->isAfter($limit)) {
                throw EventException::invalid('The preview must fit within two years. Use a shorter finite series.');
            }
            if ('MONTHLY' === $series->frequency && $date->dayOfMonth() !== $series->firstDate->dayOfMonth()) {
                throw EventException::invalid('A monthly date is missing in one of the months. Choose a day from 1 to 28, or plan individual dates.');
            }
            $windows[] = self::window($date, $series->startsAt, $series->endsAt, $series->endDayOffset, $series->timeZone);
        }

        return $windows;
    }

    private static function instant(CalendarDate $date, TimeOfDay $time, DateTimeZone $zone): DateTimeImmutable
    {
        $utc = new DateTimeZone('UTC');
        $local = DateTimeImmutable::createFromFormat('Y-m-d H:i:s.u', $date->iso().' '.substr($time->iso(), 0, 8).$time->fraction(), $utc);
        if (false === $local) {
            throw EventException::invalid('Choose a valid time of day.');
        }
        $naive = (int) $local->format('U');
        $valid = [];
        foreach ([$naive - self::DAY, $naive + self::DAY] as $probe) {
            $candidate = $zone->getOffset(new DateTimeImmutable('@'.$probe));
            if ($zone->getOffset(new DateTimeImmutable('@'.($naive - $candidate))) === $candidate) {
                $valid[$candidate] = true;
            }
        }
        if (1 !== \count($valid)) {
            throw EventException::invalid('A local time is ambiguous or does not exist because of daylight saving. Choose a different time.');
        }
        $offset = array_key_first($valid);

        return $local->modify(sprintf('%+d seconds', -$offset))->setTimezone($utc);
    }
}
