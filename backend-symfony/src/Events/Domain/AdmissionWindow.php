<?php

declare(strict_types=1);

namespace App\Events\Domain;

use DateTimeImmutable;

/** Check-in is open from two hours before a scheduled occurrence until it ends. */
final class AdmissionWindow
{
    private const int LEAD_SECONDS = 7200;

    private function __construct()
    {
    }

    public static function open(EventOccurrence $occurrence, DateTimeImmutable $now): bool
    {
        return self::isOpen($occurrence->status, $occurrence->startsAt, $occurrence->endsAt, $now);
    }

    /** The same rule for read models that carry the occurrence's status and times. */
    public static function isOpen(string $status, DateTimeImmutable $startsAt, DateTimeImmutable $endsAt, DateTimeImmutable $now): bool
    {
        return 'SCHEDULED' === $status
            && $now >= $startsAt->modify('-'.self::LEAD_SECONDS.' seconds')
            && $now < $endsAt;
    }

    public static function require(EventOccurrence $occurrence, DateTimeImmutable $now): void
    {
        if (!self::open($occurrence, $now)) {
            throw EventException::conflict('Check-in is available from two hours before a scheduled occurrence until its end.');
        }
    }
}
