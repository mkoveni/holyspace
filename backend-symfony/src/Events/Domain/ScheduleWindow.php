<?php

declare(strict_types=1);

namespace App\Events\Domain;

use App\Shared\Domain\CalendarDate;
use DateTimeImmutable;

/** One planned occurrence: the local date it belongs to and its UTC start and end. */
final readonly class ScheduleWindow
{
    public function __construct(
        public CalendarDate $originDate,
        public DateTimeImmutable $startsAt,
        public DateTimeImmutable $endsAt,
    ) {
    }
}
