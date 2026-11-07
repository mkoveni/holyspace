<?php

declare(strict_types=1);

namespace App\Events\Application\Query\ListEventCalendar;

use DateTimeImmutable;

final readonly class ListEventCalendarQuery
{
    public function __construct(
        public ?string $eventId,
        public string $search,
        public DateTimeImmutable $from,
        public DateTimeImmutable $until,
        public ?string $status,
        public int $limit,
        public int $offset,
    ) {
    }
}
