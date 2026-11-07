<?php

declare(strict_types=1);

namespace App\Volunteering\Domain;

/** Weekly availability. Times are local to the zone; dates are ISO (Y-m-d) with an exclusive end; overnight windows end next day. */
final readonly class AvailabilityRule
{
    public function __construct(
        public int $dayOfWeek,
        public string $startsAt,
        public string $endsAt,
        public string $timeZone,
        public string $validFrom,
        public string $validUntil,
    ) {
        if ($dayOfWeek < 1 || $dayOfWeek > 7 || $startsAt === $endsAt || $validUntil <= $validFrom
            || 1 !== preg_match('/^\d{2}:\d{2}:\d{2}$/', $startsAt) || 1 !== preg_match('/^\d{2}:\d{2}:\d{2}$/', $endsAt)
            || 1 !== preg_match('/^\d{4}-\d{2}-\d{2}$/', $validFrom) || 1 !== preg_match('/^\d{4}-\d{2}-\d{2}$/', $validUntil)) {
            throw VolunteerException::invalid('Use a weekly day, start/end time and an exclusive end date. Overnight windows end on the following day.');
        }
        ServingRules::zone($timeZone);
    }
}
