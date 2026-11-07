<?php

declare(strict_types=1);

namespace App\Events\Domain;

use DateTimeImmutable;
use DateTimeZone;

/** Shared bounds and validation for the Events context; messages match the Java application. */
final class EventRules
{
    private const MAX_PERIOD_DAYS = 7;
    private const MAX_RANGE_DAYS = 366;
    private const MICROS_PER_DAY = 86_400_000_000;

    private function __construct()
    {
    }

    /** Trimmed text of 1 to $max characters. */
    public static function text(?string $value, int $max): string
    {
        if (null === $value || self::blank($value) || mb_strlen($value) > $max) {
            throw EventException::invalid('Enter text of 1–'.$max.' characters.');
        }

        return self::strip($value);
    }

    public static function optional(?string $value, int $max): string
    {
        if (null === $value || self::blank($value)) {
            return '';
        }

        return self::text($value, $max);
    }

    public static function id(?string $id): string
    {
        if (null === $id || '' === $id) {
            throw EventException::invalid('An identifier is required.');
        }

        return $id;
    }

    /** PostgreSQL stores microseconds and PHP already holds them, so this only fixes the zone to UTC. */
    public static function instant(DateTimeImmutable $at): DateTimeImmutable
    {
        return $at->setTimezone(new DateTimeZone('UTC'));
    }

    /** A positive interval of at most seven days. */
    public static function period(DateTimeImmutable $from, DateTimeImmutable $until): void
    {
        if (!self::within($from, $until, self::MAX_PERIOD_DAYS)) {
            throw EventException::invalid('Choose a positive interval of at most seven days.');
        }
    }

    /** A positive range of at most 366 days. */
    public static function range(DateTimeImmutable $from, DateTimeImmutable $until): void
    {
        if (!self::within($from, $until, self::MAX_RANGE_DAYS)) {
            throw EventException::invalid('Choose a date range of at most 366 days.');
        }
    }

    /** Half-open interval overlap: touching intervals do not overlap. */
    public static function overlaps(DateTimeImmutable $start, DateTimeImmutable $end, DateTimeImmutable $from, DateTimeImmutable $until): bool
    {
        return self::micros($start) < self::micros($until) && self::micros($from) < self::micros($end);
    }

    public static function version(int $actual, int $expected): void
    {
        if ($actual !== $expected) {
            throw EventException::conflict('These details changed. Refresh and review before saving.');
        }
    }

    /** A region id (Europe/London), UTC, Z or a fixed offset (+02:00), as java.time.ZoneId.of accepts. */
    public static function zone(?string $value): DateTimeZone
    {
        $known = null !== $value && (
            \in_array($value, DateTimeZone::listIdentifiers(DateTimeZone::ALL_WITH_BC), true)
            || 1 === preg_match('/\A(?:Z|[+-](?:0\d|1[0-8]):[0-5]\d)\z/', $value)
        );
        if (!$known) {
            throw EventException::invalid('Choose a valid time zone.');
        }

        return new DateTimeZone('Z' === $value ? 'UTC' : $value);
    }

    public static function page(int $limit, int $offset): void
    {
        if ($limit < 1 || $limit > 100 || $offset < 0 || $offset > 100000) {
            throw EventException::invalid('Choose a page of 1–100 records.');
        }
    }

    private static function within(DateTimeImmutable $from, DateTimeImmutable $until, int $maxDays): bool
    {
        $length = self::micros($until) - self::micros($from);

        return $length > 0 && $length <= $maxDays * self::MICROS_PER_DAY;
    }

    private static function micros(DateTimeImmutable $at): int
    {
        return (int) $at->format('U') * 1_000_000 + (int) $at->format('u');
    }

    private static function blank(string $value): bool
    {
        return '' === self::strip($value);
    }

    private static function strip(string $value): string
    {
        return preg_replace('/\A[\s\p{Z}]+|[\s\p{Z}]+\z/u', '', $value) ?? $value;
    }
}
