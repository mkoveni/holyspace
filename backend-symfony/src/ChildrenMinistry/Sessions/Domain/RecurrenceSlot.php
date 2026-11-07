<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Sessions\Domain;

use App\Shared\Domain\CalendarDate;
use DateTimeImmutable;

/** One dated occurrence of a recurrence, resolved to instants in the location's zone. */
final readonly class RecurrenceSlot
{
    public function __construct(public CalendarDate $date, public DateTimeImmutable $startsAt, public DateTimeImmutable $endsAt, public bool $excluded)
    {
    }
}
