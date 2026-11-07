<?php

declare(strict_types=1);

namespace App\Events\Application\Contract;

use App\Events\Domain\EventOccurrence;
use DateTimeImmutable;

final readonly class OccurrenceView
{
    public function __construct(
        public string $id,
        public string $eventId,
        public string $seriesId,
        public string $originDate,
        public string $timeZone,
        public DateTimeImmutable $startsAt,
        public DateTimeImmutable $endsAt,
        public string $venueId,
        public string $venueName,
        public string $venueAddress,
        public bool $exclusive,
        public string $status,
        public bool $overridden,
        public int $version,
        public int $scheduleRevision,
    ) {
    }

    public static function of(EventOccurrence $o): self
    {
        return new self($o->id, $o->eventId, $o->seriesId, $o->originDate->iso(), $o->timeZone, $o->startsAt, $o->endsAt, $o->venueId, $o->venueName, $o->venueAddress, $o->exclusive, $o->status, $o->overridden, $o->version, $o->scheduleRevision);
    }
}
