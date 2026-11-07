<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Sessions\Domain;

use App\ChildrenMinistry\Shared\Domain\ChildrenException;
use App\Shared\Domain\CalendarDate;
use DateTimeImmutable;
use DateTimeZone;

/**
 * 1-52 dates every 1-4 weeks within 366 days, at fixed local wall times. Times are whole minutes ("HH:MM:00") and the
 * end must follow the start on the same day. Excluded dates must belong to the pattern and leave at least one session.
 */
final readonly class WeeklyRecurrence
{
    private const int DAY = 86400;

    public string $startsAt;

    public string $endsAt;

    /** @var list<CalendarDate> sorted, unique */
    public array $excludedDates;

    /** @param list<CalendarDate> $excludedDates */
    public function __construct(public CalendarDate $firstDate, string $startsAt, string $endsAt, public int $intervalWeeks, public int $occurrences, array $excludedDates = [])
    {
        $this->startsAt = self::time($startsAt);
        $this->endsAt = self::time($endsAt);
        if ($this->endsAt <= $this->startsAt || $intervalWeeks < 1 || $intervalWeeks > 4 || $occurrences < 1 || $occurrences > 52
            || $intervalWeeks * ($occurrences - 1) * 7 > 365) {
            throw ChildrenException::invalid('Choose 1–52 weekly dates, every 1–4 weeks, within 366 days, with an end time after the start on the same day.');
        }
        $pattern = [];
        for ($index = 0; $index < $occurrences; ++$index) {
            $pattern[$this->dateAt($index)->iso()] = true;
        }
        $excluded = [];
        foreach ($excludedDates as $date) {
            if (!isset($pattern[$date->iso()])) {
                throw ChildrenException::invalid('Skip only dates in this series and keep at least one session.');
            }
            $excluded[$date->iso()] = $date;
        }
        if (\count($excluded) === $occurrences) {
            throw ChildrenException::invalid('Skip only dates in this series and keep at least one session.');
        }
        ksort($excluded);
        $this->excludedDates = array_values($excluded);
    }

    /**
     * @return list<RecurrenceSlot>
     *
     * @throws ChildrenException when a wall time does not exist or happens twice on one of the dates
     */
    public function slots(DateTimeZone $zone): array
    {
        $excluded = array_map(static fn (CalendarDate $date): string => $date->iso(), $this->excludedDates);
        $slots = [];
        for ($index = 0; $index < $this->occurrences; ++$index) {
            $date = $this->dateAt($index);
            $slots[] = new RecurrenceSlot($date, self::resolve($date->iso().' '.$this->startsAt, $zone), self::resolve($date->iso().' '.$this->endsAt, $zone), \in_array($date->iso(), $excluded, true));
        }

        return $slots;
    }

    private function dateAt(int $index): CalendarDate
    {
        return $this->firstDate->plusDays($index * $this->intervalWeeks * 7);
    }

    private static function time(string $value): string
    {
        if (1 !== preg_match('/\A([01]\d|2[0-3]):([0-5]\d)(?::00)?\z/', $value, $parts)) {
            throw ChildrenException::invalid('Choose 1–52 weekly dates, every 1–4 weeks, within 366 days, with an end time after the start on the same day.');
        }

        return $parts[1].':'.$parts[2].':00';
    }

    /** The instant of a local wall time; a gap or an overlap in the zone has no single answer, so it is refused. */
    private static function resolve(string $local, DateTimeZone $zone): DateTimeImmutable
    {
        $guess = new DateTimeImmutable($local, new DateTimeZone('UTC'))->getTimestamp();
        $offsets = [];
        foreach ([-self::DAY, self::DAY] as $shift) {
            $offsets[$zone->getOffset(new DateTimeImmutable('@'.($guess + $shift)))] = true;
        }
        $valid = [];
        foreach (array_keys($offsets) as $offset) {
            $instant = $guess - $offset;
            if ($zone->getOffset(new DateTimeImmutable('@'.$instant)) === $offset) {
                $valid[] = $instant;
            }
        }
        if (1 !== \count($valid)) {
            throw ChildrenException::invalid(\sprintf('The local time %s is ambiguous or unavailable in %s. Choose another time.', substr(str_replace(' ', 'T', $local), 0, 16), $zone->getName()));
        }

        return new DateTimeImmutable('@'.$valid[0]);
    }
}
