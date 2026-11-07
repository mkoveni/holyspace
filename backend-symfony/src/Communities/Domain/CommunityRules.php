<?php

declare(strict_types=1);

namespace App\Communities\Domain;

use DateTimeImmutable;

final class CommunityRules
{
    private function __construct()
    {
    }

    public static function text(?string $value, int $max): string
    {
        $value = trim($value ?? '');
        if ($value === '' || mb_strlen($value) > $max) {
            throw CommunityException::invalid('Enter text of 1–'.$max.' characters.');
        }

        return $value;
    }

    public static function optional(?string $value, int $max): string
    {
        return trim($value ?? '') === '' ? '' : self::text($value, $max);
    }

    public static function time(DateTimeImmutable $value): DateTimeImmutable
    {
        return $value->setTime(
            (int) $value->format('H'),
            (int) $value->format('i'),
            (int) $value->format('s'),
            (int) substr($value->format('u'), 0, 6)
        );
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
}
