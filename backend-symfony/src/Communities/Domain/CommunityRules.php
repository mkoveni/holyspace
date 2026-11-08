<?php

declare(strict_types=1);

namespace App\Communities\Domain;

/** Shared input rules, with Java's messages. */
final class CommunityRules
{
    private function __construct()
    {
    }

    /** Required text: the length limit applies before surrounding whitespace is removed, as in Java. */
    public static function text(?string $value, int $max): string
    {
        if (null === $value || '' === self::strip($value) || mb_strlen($value) > $max) {
            throw CommunityException::invalid('Enter text of 1–'.$max.' characters.');
        }

        return self::strip($value);
    }

    public static function optional(?string $value, int $max): string
    {
        return null === $value || '' === self::strip($value) ? '' : self::text($value, $max);
    }

    public static function version(int $actual, int $expected): void
    {
        if ($actual !== $expected) {
            throw CommunityException::conflict('These details changed. Refresh and review before saving.');
        }
    }

    public static function page(int $limit, int $offset): void
    {
        if ($limit < 1 || $limit > 100 || $offset < 0 || $offset > 100000) {
            throw CommunityException::invalid('Choose a page of 1–100 records.');
        }
    }

    private static function strip(string $value): string
    {
        return (string) preg_replace('/\A\s+|\s+\z/u', '', $value);
    }
}
