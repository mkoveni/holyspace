<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Sessions\Application;

use DateTimeImmutable;

/** A worker assigned to an open session, present or not. */
final readonly class PlanningBooking
{
    public function __construct(public string $sessionId, public string $workerId, public DateTimeImmutable $startsAt, public DateTimeImmutable $endsAt)
    {
    }
}
