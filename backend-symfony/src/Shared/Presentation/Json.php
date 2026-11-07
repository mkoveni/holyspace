<?php

declare(strict_types=1);

namespace App\Shared\Presentation;

use DateTimeImmutable;
use DateTimeZone;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;

/** Wire-format helpers shared by every controller. */
final class Json
{
    private function __construct()
    {
    }

    /** ISO-8601 instant in UTC with trimmed fractional seconds, like Jackson's Instant output. */
    public static function instant(DateTimeImmutable $time): string
    {
        $text = $time->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s.u');
        $text = rtrim(rtrim($text, '0'), '.');

        return $text.'Z';
    }

    /** Parses an ISO-8601 instant (UTC `Z` or an offset), keeping microseconds like the database does. */
    public static function parseInstant(string $value): DateTimeImmutable
    {
        if (1 !== preg_match('/\A(\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2})(?:\.(\d{1,9}))?(Z|[+-]\d{2}:\d{2})\z/', $value, $parts)) {
            throw new BadRequestException('Timestamps must use the ISO-8601 instant format.');
        }
        $fraction = substr(str_pad($parts[2], 6, '0'), 0, 6);
        $time = DateTimeImmutable::createFromFormat('Y-m-d\TH:i:s.uP', $parts[1].'.'.$fraction.('Z' === $parts[3] ? '+00:00' : $parts[3]));
        $problems = DateTimeImmutable::getLastErrors();
        if (false === $time || (false !== $problems && ($problems['warning_count'] > 0 || $problems['error_count'] > 0))) {
            throw new BadRequestException('Timestamps must use the ISO-8601 instant format.');
        }

        return $time;
    }

    public static function uuid(string $value): string
    {
        if (1 !== preg_match('/\A[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}\z/', $value)) {
            throw new BadRequestException('Invalid identifier.');
        }

        return strtolower($value);
    }
}
