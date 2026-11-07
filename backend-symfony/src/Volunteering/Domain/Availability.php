<?php

declare(strict_types=1);

namespace App\Volunteering\Domain;

use DateTimeImmutable;
use DateTimeZone;

/** Decides whether the volunteer's rules and exceptions cover a whole interval. Unknown availability is not availability. */
final class Availability
{
    private const DAY = 86400;

    private function __construct()
    {
    }

    /**
     * @param list<AvailabilityRule>      $rules
     * @param list<AvailabilityException> $exceptions
     */
    public static function covers(DateTimeImmutable $from, DateTimeImmutable $until, array $rules, array $exceptions): bool
    {
        foreach ($exceptions as $exception) {
            if (!$exception->available && ServingRules::overlaps($exception->startsAt, $exception->endsAt, $from, $until)) {
                return false;
            }
        }

        $windows = [];
        foreach ($exceptions as $exception) {
            if ($exception->available) {
                $windows[] = [$exception->startsAt, $exception->endsAt];
            }
        }
        foreach ($rules as $rule) {
            foreach (self::ruleWindows($rule, $from, $until) as $window) {
                $windows[] = $window;
            }
        }

        usort($windows, static fn (array $a, array $b): int => $a[0] <=> $b[0]);
        $cursor = $from;
        foreach ($windows as [$start, $end]) {
            if ($end < $cursor) {
                continue;
            }
            if ($start > $cursor) {
                break;
            }
            if ($end > $cursor) {
                $cursor = $end;
            }
            if (!($cursor < $until)) {
                return true;
            }
        }

        return false;
    }

    /** @return list<array{DateTimeImmutable, DateTimeImmutable}> */
    private static function ruleWindows(AvailabilityRule $rule, DateTimeImmutable $from, DateTimeImmutable $until): array
    {
        $zone = ServingRules::zone($rule->timeZone);
        $day = $from->setTimezone($zone)->setTime(0, 0)->modify('-1 day');
        $last = $until->setTimezone($zone)->format('Y-m-d');
        $windows = [];
        for (; $day->format('Y-m-d') <= $last; $day = $day->modify('+1 day')) {
            $date = $day->format('Y-m-d');
            if ((int) $day->format('N') !== $rule->dayOfWeek || $date < $rule->validFrom || $date >= $rule->validUntil) {
                continue;
            }
            $endDate = $rule->endsAt > $rule->startsAt ? $date : $day->modify('+1 day')->format('Y-m-d');
            $start = self::resolve($date.' '.$rule->startsAt, $zone);
            $end = self::resolve($endDate.' '.$rule->endsAt, $zone);
            if (null !== $start && null !== $end) {
                $windows[] = [$start, $end];
            }
        }

        return $windows;
    }

    /** The instant for a local time, or null when the zone skips it (gap) or repeats it (overlap). */
    private static function resolve(string $local, DateTimeZone $zone): ?DateTimeImmutable
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

        return 1 === \count($valid) ? new DateTimeImmutable('@'.$valid[0]) : null;
    }
}
