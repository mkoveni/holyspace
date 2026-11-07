<?php

declare(strict_types=1);

namespace App\Volunteering\Application\Contract;

use DateTimeImmutable;

/** Published activity metadata. Source aggregates and persistence remain private to the originating context. */
final readonly class SourceActivity
{
    public function __construct(
        public string $id,
        public string $label,
        public DateTimeImmutable $startsAt,
        public DateTimeImmutable $endsAt,
        public bool $active,
        public ?string $scheduleRevision = null,
        public ?string $venueLabel = null,
    ) {
    }
}
