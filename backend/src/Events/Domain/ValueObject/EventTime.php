<?php

declare(strict_types=1);

namespace App\Events\Domain\ValueObject;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

final class EventTime
{
    public static function local(string $value, string $timezone): DateTimeImmutable
    {
        if (!in_array($timezone, DateTimeZone::listIdentifiers(), true)) {
            throw new InvalidArgumentException('Choose a valid timezone.');
        }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $value, new DateTimeZone($timezone));
        if (false === $date || $date->format('Y-m-d\TH:i') !== $value) {
            throw new InvalidArgumentException('Enter a valid local date and time (YYYY-MM-DDTHH:mm).');
        }

        return $date;
    }
}
