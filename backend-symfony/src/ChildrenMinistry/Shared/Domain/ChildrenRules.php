<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Shared\Domain;

use App\Shared\Domain\CalendarDate;
use DateTimeImmutable;

/** Rules every Children Ministry module shares: bounded text, validity periods, optimistic versions and age checks. */
final class ChildrenRules
{
    private function __construct()
    {
    }

    public static function text(?string $value, int $max): string
    {
        $text = self::strip($value ?? '');
        if ('' === $text || mb_strlen($text) > $max) {
            throw ChildrenException::invalid(sprintf('A value is missing or exceeds its maximum length of %d.', $max));
        }

        return $text;
    }

    public static function optional(?string $value, int $max): string
    {
        return '' === self::strip($value ?? '') ? '' : self::text($value, $max);
    }

    public static function period(?CalendarDate $from, ?CalendarDate $until): void
    {
        if (null === $from || null === $until || !$until->isAfter($from)) {
            throw ChildrenException::invalid('Validity end must follow its start.');
        }
    }

    /** A validity window given as instants: both ends are required and the end must follow the start. */
    public static function window(?DateTimeImmutable $from, ?DateTimeImmutable $until): void
    {
        if (null === $from || null === $until || $until <= $from) {
            throw ChildrenException::invalid('Validity end must follow its start.');
        }
    }

    /** The interval [start, end) covers [from, until]: it starts no later and ends no earlier. */
    public static function covers(DateTimeImmutable $start, DateTimeImmutable $end, DateTimeImmutable $from, DateTimeImmutable $until): bool
    {
        return $start <= $from && $from < $end && $end >= $until;
    }

    public static function version(int $actual, int $expected): void
    {
        if ($actual !== $expected || $expected < 0) {
            throw ChildrenException::conflict('This record changed. Reload before trying again.');
        }
    }

    /**
     * The birth date must be recorded and the person must still be under 18 on the day.
     *
     * @return CalendarDate the validated birth date
     */
    public static function child(?CalendarDate $birth, CalendarDate $on): CalendarDate
    {
        if (null === $birth || $birth->isAfter($on) || !$birth->plusYears(18)->isAfter($on)) {
            throw ChildrenException::invalid('A recorded birth date identifying a child under 18 is required.');
        }

        return $birth;
    }

    /**
     * The birth date must be recorded and the person must be 18 or older on the day.
     *
     * @return CalendarDate the validated birth date
     */
    public static function adult(?CalendarDate $birth, CalendarDate $on): CalendarDate
    {
        if (null === $birth || $birth->isAfter($on) || $birth->plusYears(18)->isAfter($on)) {
            throw ChildrenException::invalid('A recorded birth date confirming adulthood is required.');
        }

        return $birth;
    }

    public static function page(int $limit, int $offset): void
    {
        if ($limit < 1 || $limit > 100 || $offset < 0 || $offset > 1000000) {
            throw ChildrenException::invalid('Invalid paging parameters.');
        }
    }

    private static function strip(string $value): string
    {
        return preg_replace('/\A[\s\p{Z}]+|[\s\p{Z}]+\z/u', '', $value) ?? '';
    }
}
