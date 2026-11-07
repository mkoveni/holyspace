<?php

declare(strict_types=1);

namespace App\Events\Application;

use DateTimeImmutable;
use DateTimeZone;

final class Fingerprint
{
    private function __construct()
    {
    }

    public static function of(string|int|bool|DateTimeImmutable|null ...$values): string
    {
        $data = '';
        foreach ($values as $value) {
            $text = self::render($value);
            $data .= pack('N', \strlen($text)).$text;
        }

        return hash('sha256', $data);
    }

    /** Formats like java.time.Instant#toString: fraction omitted when zero, otherwise 3 or 6 digits. */
    public static function instant(DateTimeImmutable $instant): string
    {
        $utc = $instant->setTimezone(new DateTimeZone('UTC'));
        $micros = $utc->format('u');
        $fraction = '000000' === $micros ? '' : ('000' === substr($micros, 3) ? '.'.substr($micros, 0, 3) : '.'.$micros);

        return $utc->format('Y-m-d\TH:i:s').$fraction.'Z';
    }

    private static function render(string|int|bool|DateTimeImmutable|null $value): string
    {
        return match (true) {
            null === $value => 'null',
            true === $value => 'true',
            false === $value => 'false',
            $value instanceof DateTimeImmutable => self::instant($value),
            default => (string) $value,
        };
    }
}
