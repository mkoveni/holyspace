<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Incidents\Application\Contract;

use App\ChildrenMinistry\Incidents\Application\IncidentReceipt;
use App\ChildrenMinistry\Incidents\Application\IncidentVisits;
use App\ChildrenMinistry\Incidents\Domain\IncidentDetails;
use App\ChildrenMinistry\Incidents\Domain\IncidentEvent;
use App\Shared\Domain\CalendarDate;
use DateTimeImmutable;

/** Read side of incidents. Details and events are restricted: only the audited access use case may call them. */
interface IncidentReader
{
    public function receipt(string $actorId, string $requestId): ?IncidentReceipt;

    public function find(string $id): ?IncidentSummary;

    public function details(string $id): ?IncidentDetails;

    /** @return list<IncidentEvent> newest first */
    public function events(string $id, int $limit, int $offset): array;

    public function eventCount(string $id): int;

    /** Whether the segment belongs to the child in the session and spans the instant (the end is exclusive). */
    public function attended(string $childId, string $sessionId, string $segmentId, DateTimeImmutable $at): bool;

    /**
     * Incidents still within retention, by review date. Totals of overdue and unassigned active incidents ignore the
     * overdue-only filter and the paging.
     *
     * @param string $status ALL, ACTIVE (not closed), OPEN, IN_REVIEW or CLOSED
     */
    public function list(?string $sessionId, ?string $childId, string $status, bool $overdueOnly, CalendarDate $on, int $limit, int $offset): IncidentPage;

    /**
     * Attendance segments of the session, newest first.
     *
     * @param list<string>|null $matchingChildIds null for every child; an empty list matches none
     */
    public function visits(string $sessionId, ?array $matchingChildIds, int $limit, int $offset): IncidentVisits;
}
