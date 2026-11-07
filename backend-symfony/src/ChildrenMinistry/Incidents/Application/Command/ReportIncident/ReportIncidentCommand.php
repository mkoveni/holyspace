<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Incidents\Application\Command\ReportIncident;

use App\Shared\Application\Command;
use App\Shared\Domain\CalendarDate;
use DateTimeImmutable;

/** @implements Command<string> the incident id, the same on every retry of the request */
final readonly class ReportIncidentCommand implements Command
{
    public function __construct(
        public string $actorId,
        public string $requestId,
        public string $childId,
        public string $sessionId,
        public string $segmentId,
        public ?string $category,
        public ?string $severity,
        public DateTimeImmutable $occurredAt,
        public ?string $description,
        public ?string $immediateAction,
        public CalendarDate $reviewOn,
        public CalendarDate $retainUntil,
    ) {
    }

    public function __debugInfo(): array
    {
        return ['restricted' => true];
    }
}
