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

    /** Java `ServingRules.text`: required, at most `$max` characters before stripping, returned stripped. */
    public static function text(?string $value, int $max): string
    {
        $stripped = null === $value ? '' : (string) preg_replace('/\A\s+|\s+\z/u', '', $value);
        if ('' === $stripped || mb_strlen((string) $value) > $max) {
            throw VolunteerException::invalid('Enter text of at most '.$max.' characters.');
        }

        return $stripped;
    }

    public static function version(int $actual, int $expected): void
    {
        if ($actual !== $expected) {
            throw VolunteerException::conflict('This record changed. Refresh before saving.');
        }
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

    /** Java `ServingRules.id`. */
    public static function id(?string $id): string
    {
        if (null === $id || '' === $id) {
            throw VolunteerException::invalid('An identifier is required.');
        }

        return $id;
    }

    /** Java `ServingRules.instant`: required, truncated to microseconds (PHP instants never carry more). */
    public static function instant(?DateTimeImmutable $value): DateTimeImmutable
    {
        return $value ?? throw VolunteerException::invalid('A timestamp is required.');
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
