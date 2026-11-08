<?php

declare(strict_types=1);

namespace App\Volunteering\Domain;

use DateTimeImmutable;
use DateTimeZone;

/** Java `ZoneRules.getValidOffsets(local).size() == 1`: a local time maps to one instant, or it is skipped or repeated. */
final class ZonedTime
{
    private const int DAY = 86400;

    private function __construct()
    {
    }

    /** The instant for a local `Y-m-d H:i:s` time, or null when the zone skips it (gap) or repeats it (overlap). */
    public static function resolve(string $local, DateTimeZone $zone): ?DateTimeImmutable
    {
        $guess = new DateTimeImmutable($local, new DateTimeZone('UTC'))->getTimestamp();
        $offsets = [];
        foreach ([-self::DAY, self::DAY] as $shift) {
            $offsets[$zone->getOffset(new DateTimeImmutable('@'.($guess + $shift)))] = true;
        }
        $valid = [];
        foreach (array_keys($offsets) as $offset) {
            $instant = $guess - $offset;
            if ($zone->getOffset(new DateTimeImmutable('@'.$instant)) === $offset) {
                $valid[] = $instant;
            }
        }

        return 1 === \count($valid) ? new DateTimeImmutable('@'.$valid[0]) : null;
    }
}
