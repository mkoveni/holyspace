<?php

declare(strict_types=1);

namespace App\Tests\ChildrenMinistry\Incidents\Application;

use App\ChildrenMinistry\Incidents\Application\Contract\IncidentPage;
use App\ChildrenMinistry\Incidents\Application\Contract\IncidentReader;
use App\ChildrenMinistry\Incidents\Application\Contract\IncidentSummary;
use App\ChildrenMinistry\Incidents\Application\IncidentReceipt;
use App\ChildrenMinistry\Incidents\Application\IncidentVisit;
use App\ChildrenMinistry\Incidents\Application\IncidentVisits;
use App\ChildrenMinistry\Incidents\Domain\Incident;
use App\ChildrenMinistry\Incidents\Domain\IncidentDetails;
use App\ChildrenMinistry\Incidents\Domain\IncidentEvent;
use App\ChildrenMinistry\Incidents\Domain\IncidentStatus;
use App\ChildrenMinistry\Incidents\Domain\Repository\IncidentRepository;
use App\ChildrenMinistry\Shared\Domain\ChildrenException;
use App\Shared\Domain\CalendarDate;
use App\Tests\ChildrenMinistry\Shared\CallLog;
use DateTimeImmutable;

/** Incidents, details and history in memory; the log records locks and writes in order. */
final class IncidentWorld implements IncidentReader, IncidentRepository
{
    /** @var array<string, Incident> */
    public array $incidents = [];

    /** @var array<string, IncidentDetails> */
    public array $details = [];

    /** @var list<IncidentEvent> */
    public array $events = [];

    /** @var array<string, array{string, string}> actor.request => [incident id, hash] */
    public array $receipts = [];

    /** @var list<IncidentVisit> */
    public array $visits = [];

    public function __construct(public readonly CallLog $log)
    {
    }

    public function lock(string $id): ?Incident
    {
        $this->log->entries[] = 'lock:incident';

        return $this->incidents[$id] ?? null;
    }

    public function insert(Incident $incident, IncidentDetails $details, string $requestId, string $requestHash): void
    {
        $this->log->entries[] = 'insert:incident';
        $this->incidents[$incident->id] = $incident;
        $this->details[$incident->id] = $details;
        $this->receipts[$incident->reportedBy.'.'.$requestId] = [$incident->id, $requestHash];
    }

    public function update(Incident $incident): void
    {
        $this->log->entries[] = 'update:incident';
        $this->incidents[$incident->id] = $incident;
    }

    public function append(IncidentEvent $event): void
    {
        $this->log->entries[] = 'append:'.$event->type->value;
        $this->events[] = $event;
    }

    public function purgeExpired(CalendarDate $on, int $limit): int
    {
        $this->log->entries[] = 'purge';
        $expired = array_keys(array_filter($this->incidents, fn (Incident $i): bool => $i->retainUntil->isBefore($on) && isset($this->details[$i->id])));
        $expired = \array_slice($expired, 0, $limit);
        foreach ($expired as $id) {
            unset($this->details[$id]);
        }

        return \count($expired);
    }

    public function receipt(string $actorId, string $requestId): ?IncidentReceipt
    {
        $receipt = $this->receipts[$actorId.'.'.$requestId] ?? null;

        return null === $receipt ? null : new IncidentReceipt($receipt[0], $receipt[1]);
    }

    public function find(string $id): ?IncidentSummary
    {
        $i = $this->incidents[$id] ?? null;

        return null === $i ? null : new IncidentSummary($i->id, $i->childId, $i->sessionId, $i->segmentId, $i->category, $i->severity, $i->occurredAt, $i->reportedBy, $i->reportedAt, $i->status, $i->ownerId, $i->reviewOn, $i->retainUntil, $i->version);
    }

    public function details(string $id): ?IncidentDetails
    {
        $this->log->entries[] = 'read:details';

        return $this->details[$id] ?? null;
    }

    public function events(string $id, int $limit, int $offset): array
    {
        $this->log->entries[] = 'read:events';

        return \array_slice(array_reverse(array_values(array_filter($this->events, static fn (IncidentEvent $e): bool => $e->incidentId === $id))), $offset, $limit);
    }

    public function eventCount(string $id): int
    {
        return \count(array_filter($this->events, static fn (IncidentEvent $e): bool => $e->incidentId === $id));
    }

    public function attended(string $childId, string $sessionId, string $segmentId, DateTimeImmutable $at): bool
    {
        foreach ($this->visits as $visit) {
            if ($visit->segmentId === $segmentId && $visit->childId === $childId && $visit->arrivedAt <= $at && (null === $visit->leftAt || $visit->leftAt > $at)) {
                return true;
            }
        }

        return false;
    }

    public function list(?string $sessionId, ?string $childId, string $status, bool $overdueOnly, CalendarDate $on, int $limit, int $offset): IncidentPage
    {
        $all = array_values(array_filter(array_map(fn (string $id): ?IncidentSummary => $this->find($id), array_keys($this->incidents)), static fn (?IncidentSummary $i): bool => null !== $i && !$i->retainUntil->isBefore($on) && ('ALL' === $status || ('ACTIVE' === $status ? IncidentStatus::Closed !== $i->status : $i->status->value === $status))));

        return new IncidentPage(\array_slice($all, $offset, $limit), \count($all), 0, 0);
    }

    public function visits(string $sessionId, ?array $matchingChildIds, int $limit, int $offset): IncidentVisits
    {
        $all = array_values(array_filter($this->visits, static fn (IncidentVisit $v): bool => null === $matchingChildIds || \in_array($v->childId, $matchingChildIds, true)));

        return new IncidentVisits(\array_slice($all, $offset, $limit), \count($all));
    }

    public function requireIncident(string $id): Incident
    {
        return $this->incidents[$id] ?? throw ChildrenException::missing('Incident');
    }
}
