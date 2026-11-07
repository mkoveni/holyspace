<?php

declare(strict_types=1);

namespace App\Giving\Domain;

/**
 * java.lang.String semantics that Giving's limits and hashes depend on: length counts UTF-16 code units,
 * trim() removes characters up to U+0020, and isBlank()/stripLeading() test Character.isWhitespace.
 */
final class JavaString
{
    /** Character.isWhitespace: Unicode space separators except the no-break spaces, plus the ASCII and file separators. */
    private const string WHITESPACE = '(?:[\t\n\x{0B}\f\r\x{1C}-\x{1F}]|(?![\x{A0}\x{2007}\x{202F}])[\p{Zs}\p{Zl}\p{Zp}])';

    private function __construct()
    {
    }

    /** String.length(): characters outside the Basic Multilingual Plane count twice. */
    public static function length(string $value): int
    {
        return \strlen(mb_convert_encoding($value, 'UTF-16LE', 'UTF-8')) >> 1;
    }

    /** String.trim(): only control characters and the ASCII space, never Unicode spaces. */
    public static function trim(string $value): string
    {
        return trim($value, "\x00..\x20");
    }

    /** String.isBlank(): Character.isWhitespace excludes the no-break spaces U+00A0, U+2007 and U+202F. */
    public static function isBlank(string $value): bool
    {
        return 1 === preg_match('/\A'.self::WHITESPACE.'*\z/u', $value);
    }

    /** String.stripLeading(): removes leading Character.isWhitespace characters. */
    public static function stripLeading(string $value): string
    {
        return (string) preg_replace('/\A'.self::WHITESPACE.'+/u', '', $value);
    }
}
