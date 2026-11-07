<?php

declare(strict_types=1);

namespace App\Volunteering\Domain;

use DateTimeImmutable;
use DateTimeZone;
use Exception;

final class ServingRules
{
    private function __construct()
    {
    }

    /** True when [$from, $until] contains [$start, $end]. */
    public static function covers(DateTimeImmutable $from, DateTimeImmutable $until, DateTimeImmutable $start, DateTimeImmutable $end): bool
    {
        return !($from > $start) && !($until < $end);
    }

    /** True when the half-open intervals share any time. */
    public static function overlaps(DateTimeImmutable $from, DateTimeImmutable $until, DateTimeImmutable $start, DateTimeImmutable $end): bool
    {
        return $from < $end && $start < $until;
    }

    public static function period(DateTimeImmutable $from, DateTimeImmutable $until): void
    {
        if (!($until > $from)) {
            throw VolunteerException::invalid('The end must follow the start.');
        }
    }

    public static function zone(string $value): DateTimeZone
    {
        try {
            return new DateTimeZone($value);
        } catch (Exception) {
            throw VolunteerException::invalid('Choose a valid time zone.');
        }
    }
}
