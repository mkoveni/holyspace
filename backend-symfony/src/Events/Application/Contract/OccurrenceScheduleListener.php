<?php

declare(strict_types=1);

namespace App\Events\Application\Contract;

use DateTimeImmutable;

/** Synchronous projections of an occurrence's start time. They run inside the scheduling transaction. */
interface OccurrenceScheduleListener
{
    public function changed(string $occurrenceId, DateTimeImmutable $startsAt): void;
}
