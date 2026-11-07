<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Persistence;

use DateTimeImmutable;
use DateTimeZone;

/** Converts between PHP instants and the TIMESTAMPTZ strings the pdo_pgsql driver exchanges. */
final class Timestamps
{
    private function __construct()
    {
    }

    public static function toDatabase(DateTimeImmutable $time): string
    {
        return $time->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s.uP');
    }

    public static function fromDatabase(string $value): DateTimeImmutable
    {
        return new DateTimeImmutable($value);
    }
}
