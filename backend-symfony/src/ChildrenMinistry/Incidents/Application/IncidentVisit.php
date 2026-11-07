<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Incidents\Application;

use DateTimeImmutable;

/** One continuous stretch of a child's attendance in a session. An incident must fall inside one. */
final readonly class IncidentVisit
{
    public function __construct(public string $segmentId, public string $childId, public DateTimeImmutable $arrivedAt, public ?DateTimeImmutable $leftAt)
    {
    }
}
