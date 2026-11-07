<?php

declare(strict_types=1);

namespace App\Events\Application\Contract;

use DateTimeImmutable;

/** One scheduled date as other contexts see it. The id is null in a preview, and the revision moves when the date does. */
final readonly class CalendarOccurrence
{
    public function __construct(
        public ?string $id,
        public DateTimeImmutable $startsAt,
        public DateTimeImmutable $endsAt,
        public string $timeZone,
        public string $venue,
        public string $status,
        public int $revision,
    ) {
    }
}
