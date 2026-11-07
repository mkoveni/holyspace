<?php

declare(strict_types=1);

namespace App\Events\Application\Contract;

use DateTimeImmutable;

/** What a member may see of an occurrence: event details, seat availability and their own booking. No roster, no staff notes. */
final readonly class MemberEventItem
{
    public function __construct(
        public string $id,
        public string $eventId,
        public string $title,
        public string $description,
        public DateTimeImmutable $startsAt,
        public DateTimeImmutable $endsAt,
        public string $timeZone,
        public string $venueName,
        public string $venueAddress,
        public string $status,
        public bool $registrationOpen,
        public bool $waitlistEnabled,
        public ?int $remainingPlaces,
        public ?MemberBooking $booking,
    ) {
    }
}
