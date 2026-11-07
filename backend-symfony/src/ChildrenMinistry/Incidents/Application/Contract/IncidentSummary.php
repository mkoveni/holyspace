<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Incidents\Application\Contract;

use App\ChildrenMinistry\Incidents\Domain\IncidentCategory;
use App\ChildrenMinistry\Incidents\Domain\IncidentSeverity;
use App\ChildrenMinistry\Incidents\Domain\IncidentStatus;
use App\Shared\Domain\CalendarDate;
use DateTimeImmutable;

/** An incident's metadata. Descriptions, actions and notes are never part of it. */
final readonly class IncidentSummary
{
    public function __construct(
        public string $id,
        public string $childId,
        public string $sessionId,
        public string $segmentId,
        public IncidentCategory $category,
        public IncidentSeverity $severity,
        public DateTimeImmutable $occurredAt,
        public string $reportedBy,
        public DateTimeImmutable $reportedAt,
        public IncidentStatus $status,
        public ?string $ownerId,
        public CalendarDate $reviewOn,
        public CalendarDate $retainUntil,
        public int $version,
    ) {
    }
}
