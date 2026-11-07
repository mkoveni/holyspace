<?php

declare(strict_types=1);

namespace App\People\Domain;

final class PeopleRules
{
    private function __construct()
    {
    }

    public static function strip(string $value): string
    {
        return preg_replace('/\A[\s\p{Z}]+|[\s\p{Z}]+\z/u', '', $value) ?? '';
    }

    public static function text(?string $value, string $name, int $max): string
    {
        $text = self::strip($value ?? '');
        if ('' === $text || mb_strlen($text) > $max) {
            throw PeopleException::invalid(sprintf('%s is required and must be at most %d characters.', $name, $max));
        }

        return $text;
    }

    public static function optionalText(?string $value, string $name, int $max): string
    {
        return '' === self::strip($value ?? '') ? '' : self::text($value, $name, $max);
    }

    public static function titleCode(?string $value): ?string
    {
        if ('' === self::strip($value ?? '')) {
            return null;
        }
        $code = mb_strtoupper(self::text($value, 'Title code', 20));
        if (1 !== preg_match('/\A[A-Z][A-Z0-9_]{0,19}\z/', $code)) {
            throw PeopleException::invalid('Invalid title code.');
        }

        return $code;
    }

    /** Optimistic concurrency: the caller must have seen the current version. */
    public static function version(int $actual, int $expected): void
    {
        if ($expected < 0 || $actual !== $expected) {
            throw PeopleException::conflict('The record has changed. Reload and try again.');
        }
    }
}
