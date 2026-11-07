<?php

declare(strict_types=1);

namespace App\Volunteering\Application;

use DateTimeImmutable;
use DateTimeZone;

/**
 * SHA-256 over length-prefixed values, byte-compatible with the Java implementation because shift source
 * revisions written by Java must compare equal to the ones computed here.
 */
final class Fingerprint
{
    private function __construct()
    {
    }

    public static function of(?string ...$values): string
    {
        $data = '';
        foreach ($values as $value) {
            $text = $value ?? 'null';
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
}
