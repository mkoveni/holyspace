<?php

declare(strict_types=1);

namespace App\Events\Application\Contract;

use DateTimeImmutable;

final readonly class OccurrenceHistoryEntry
{
    public function __construct(
        public string $id,
        public string $action,
        public string $reason,
        public DateTimeImmutable $startsAt,
        public DateTimeImmutable $endsAt,
        public string $venueId,
        public string $venueName,
        public string $venueAddress,
        public string $status,
        public int $version,
        public int $scheduleRevision,
        public DateTimeImmutable $occurredAt,
    ) {
    }
}
