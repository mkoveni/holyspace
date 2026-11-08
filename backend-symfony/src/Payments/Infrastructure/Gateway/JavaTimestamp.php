<?php

declare(strict_types=1);

namespace App\Payments\Infrastructure\Gateway;

use DateTimeImmutable;
use DateTimeZone;

/** `OffsetDateTime.now(Clock.systemUTC()).toString()`: seconds and fraction only when non-zero, then `Z`. */
final class JavaTimestamp
{
    private function __construct()
    {
    }

    public static function utc(DateTimeImmutable $time): string
    {
        $time = $time->setTimezone(new DateTimeZone('UTC'));
        $text = $time->format('Y-m-d\TH:i');
        $micros = (int) $time->format('u');
        if ((int) $time->format('s') > 0 || $micros > 0) {
            $text .= $time->format(':s');
        }
        if ($micros > 0) {
            $text .= '.'.(0 === $micros % 1000 ? substr($time->format('u'), 0, 3) : $time->format('u'));
        }

        return $text.'Z';
    }
}
