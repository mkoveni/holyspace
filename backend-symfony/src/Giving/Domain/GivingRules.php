<?php

declare(strict_types=1);

namespace App\Giving\Domain;

use App\Shared\Domain\CalendarDate;

/** Shared bounds and validation for the Giving context; messages match the Java application. */
final class GivingRules
{
    public const int MAX_LIMIT = 100;
    public const int MAX_OFFSET = 100_000;

    private function __construct()
    {
    }

    /** Required text, trimmed, of at most $max characters (counted as Java does). */
    public static function text(?string $value, int $max, string $label): string
    {
        if (null === $value || JavaString::isBlank($value) || JavaString::length(JavaString::trim($value)) > $max) {
            throw GivingException::invalid($label.' is required and must be at most '.$max.' characters.');
        }

        return JavaString::trim($value);
    }

    public static function version(int $actual, int $expected): void
    {
        if ($actual !== $expected) {
            throw GivingException::conflict('This record changed. Refresh before trying again.');
        }
    }

    /** A received, reversal or reconciliation date from 1900 through the church's today; returns the checked date. */
    public static function date(?CalendarDate $date, CalendarDate $today): CalendarDate
    {
        if (null === $date || $date->isAfter($today) || $date->year() < 1900) {
            throw GivingException::invalid('Choose a date from 1900 through today.');
        }

        return $date;
    }

    public static function page(int $limit, int $offset): void
    {
        if ($limit < 1 || $limit > self::MAX_LIMIT || $offset < 0 || $offset > self::MAX_OFFSET) {
            throw GivingException::invalid('Use a page size of 1–100 and an offset of 0–100000.');
        }
    }
}
