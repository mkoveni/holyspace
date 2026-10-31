<?php

declare(strict_types=1);

namespace App\Shared\Domain\ValueObject;

use DateTimeImmutable;
use DateTimeZone;
use Exception;
use InvalidArgumentException;
use Symfony\Component\Uid\Uuid;

final class Input
{
    public static function id(string $value): void
    {
        if (!Uuid::isValid($value)) {
            throw new InvalidArgumentException('Invalid identifier.');
        }
    }

    public static function text(string $value, int $max = 255): void
    {
        if ('' === trim($value) || mb_strlen($value) > $max) {
            throw new InvalidArgumentException('A non-empty value of at most '.$max.' characters is required.');
        }
    }

    public static function date(string $value): DateTimeImmutable
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value, new DateTimeZone('UTC'));
        if (!$date || $date->format('Y-m-d') !== $value) {
            throw new InvalidArgumentException('Invalid date; use YYYY-MM-DD.');
        }

        return $date;
    }

    public static function instant(string $value): DateTimeImmutable
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}(:\d{2}(?:\.\d{1,6})?)?(Z|[+-]\d{2}:\d{2})$/', $value)) {
            throw new InvalidArgumentException('Time must include an explicit timezone offset.');
        }
        try {
            $date = new DateTimeImmutable($value);
        } catch (Exception) {
            throw new InvalidArgumentException('Invalid time.');
        }
        if ($date->format('Y-m-d\TH:i') !== substr($value, 0, 16) || (isset($value[16]) && ':' === $value[16] && $date->format('s') !== substr($value, 17, 2))) {
            throw new InvalidArgumentException('Invalid date or time.');
        }

        return $date;
    }

    public static function interval(string $start, string $end): void
    {
        if (self::instant($end) <= self::instant($start)) {
            throw new InvalidArgumentException('End time must be after start time.');
        }
    }

    public static function overlaps(string $a, string $b, string $c, string $d): bool
    {
        return self::instant($a) < self::instant($d) && self::instant($c) < self::instant($b);
    }
}
