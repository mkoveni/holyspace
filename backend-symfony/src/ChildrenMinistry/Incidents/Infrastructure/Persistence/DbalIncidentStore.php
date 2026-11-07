<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Incidents\Infrastructure\Persistence;

use App\ChildrenMinistry\Incidents\Application\Contract\ActiveIncidents;
use App\ChildrenMinistry\Incidents\Application\Contract\IncidentCountSummary;
use App\ChildrenMinistry\Incidents\Application\Contract\IncidentPage;
use App\ChildrenMinistry\Incidents\Application\Contract\IncidentReader;
use App\ChildrenMinistry\Incidents\Application\Contract\IncidentSummary;
use App\ChildrenMinistry\Incidents\Application\Contract\SessionIncidentCounts;
use App\ChildrenMinistry\Incidents\Application\IncidentReceipt;
use App\ChildrenMinistry\Incidents\Application\IncidentVisit;
use App\ChildrenMinistry\Incidents\Application\IncidentVisits;
use App\ChildrenMinistry\Incidents\Domain\Incident;
use App\ChildrenMinistry\Incidents\Domain\IncidentCategory;
use App\ChildrenMinistry\Incidents\Domain\IncidentDetails;
use App\ChildrenMinistry\Incidents\Domain\IncidentEvent;
use App\ChildrenMinistry\Incidents\Domain\IncidentEventType;
use App\ChildrenMinistry\Incidents\Domain\IncidentSeverity;
use App\ChildrenMinistry\Incidents\Domain\IncidentStatus;
use App\ChildrenMinistry\Incidents\Domain\Repository\IncidentRepository;
use App\ChildrenMinistry\Shared\Domain\ChildrenException;
use App\Shared\Domain\CalendarDate;
use App\Shared\Infrastructure\Persistence\Timestamps;
use DateTimeImmutable;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;

/**
 * Incidents in `children_incidents`, `children_incident_details` and `children_incident_events`. The attendance
 * segment table is read for the "was the child there" check, exactly as the Java store does.
 */
final readonly class DbalIncidentStore implements IncidentReader, IncidentRepository, SessionIncidentCounts, ActiveIncidents
{
    private const string ACTIVE_IN_RETENTION = "status <> 'CLOSED' AND retain_until >= :on";

    public function __construct(private Connection $connection)
    {
    }

    public function lock(string $id): ?Incident
    {
        $row = $this->connection->fetchAssociative('SELECT * FROM children_incidents WHERE id = :id FOR UPDATE', ['id' => $id]);

        return false === $row ? null : self::incident($row);
    }

    public function insert(Incident $incident, IncidentDetails $details, string $requestId, string $requestHash): void
    {
        $this->connection->insert('children_incidents', [
            'id' => $incident->id,
            'child_id' => $incident->childId,
            'session_id' => $incident->sessionId,
            'segment_id' => $incident->segmentId,
            'category' => $incident->category->value,
            'severity' => $incident->severity->value,
            'occurred_at' => Timestamps::toDatabase($incident->occurredAt),
            'reported_by' => $incident->reportedBy,
            'reported_at' => Timestamps::toDatabase($incident->reportedAt),
            'request_id' => $requestId,
            'request_hash' => $requestHash,
            'status' => $incident->status->value,
            'review_on' => $incident->reviewOn->iso(),
            'retain_until' => $incident->retainUntil->iso(),
        ]);
        $this->connection->insert('children_incident_details', ['incident_id' => $incident->id, 'description' => $details->description, 'immediate_action' => $details->immediateAction]);
    }

    public function update(Incident $incident): void
    {
        $changed = $this->connection->update(
            'children_incidents',
            ['status' => $incident->status->value, 'owner_id' => $incident->ownerId, 'review_on' => $incident->reviewOn->iso(), 'version' => $incident->version],
            ['id' => $incident->id, 'version' => $incident->version - 1],
        );
        if (1 !== $changed) {
            throw ChildrenException::conflict('Record changed. Reload and try again.');
        }
    }

    public function append(IncidentEvent $event): void
    {
        $this->connection->insert('children_incident_events', [
            'id' => $event->id,
            'incident_id' => $event->incidentId,
            'revision' => $event->revision,
            'type' => $event->type->value,
            'actor_id' => $event->actorId,
            'occurred_at' => Timestamps::toDatabase($event->occurredAt),
            'note' => $event->note,
            'guardian_id' => $event->guardianId,
        ]);
    }

    public function purgeExpired(CalendarDate $on, int $limit): int
    {
        /** @var list<string> $ids */
        $ids = $this->connection->fetchFirstColumn(
            'SELECT i.id FROM children_incidents i WHERE i.retain_until < :on AND EXISTS (SELECT 1 FROM children_incident_details d WHERE d.incident_id = i.id) ORDER BY i.id LIMIT :limit FOR UPDATE',
            ['on' => $on->iso(), 'limit' => $limit],
        );
        if ([] === $ids) {
            return 0;
        }
        $this->connection->executeStatement("UPDATE children_incident_events SET note = '', guardian_id = NULL WHERE incident_id IN (:ids)", ['ids' => $ids], ['ids' => ArrayParameterType::STRING]);

        return (int) $this->connection->executeStatement('DELETE FROM children_incident_details WHERE incident_id IN (:ids)', ['ids' => $ids], ['ids' => ArrayParameterType::STRING]);
    }

    public function receipt(string $actorId, string $requestId): ?IncidentReceipt
    {
        $row = $this->connection->fetchAssociative('SELECT id, request_hash FROM children_incidents WHERE reported_by = :actor AND request_id = :request', ['actor' => $actorId, 'request' => $requestId]);

        return false === $row ? null : new IncidentReceipt((string) $row['id'], trim((string) $row['request_hash']));
    }

    public function find(string $id): ?IncidentSummary
    {
        $row = $this->connection->fetchAssociative('SELECT * FROM children_incidents WHERE id = :id', ['id' => $id]);

        return false === $row ? null : self::summary($row);
    }

    public function details(string $id): ?IncidentDetails
    {
        $row = $this->connection->fetchAssociative('SELECT description, immediate_action FROM children_incident_details WHERE incident_id = :id', ['id' => $id]);

        return false === $row ? null : new IncidentDetails((string) $row['description'], (string) $row['immediate_action']);
    }

    public function events(string $id, int $limit, int $offset): array
    {
        $rows = $this->connection->fetchAllAssociative('SELECT * FROM children_incident_events WHERE incident_id = :id ORDER BY revision DESC LIMIT :limit OFFSET :offset', ['id' => $id, 'limit' => $limit, 'offset' => $offset]);

        return array_map(static fn (array $row): IncidentEvent => new IncidentEvent(
            (string) $row['id'],
            (string) $row['incident_id'],
            (int) $row['revision'],
            IncidentEventType::from((string) $row['type']),
            (string) $row['actor_id'],
            Timestamps::fromDatabase((string) $row['occurred_at']),
            '' === (string) $row['note'] ? '(removed after retention)' : (string) $row['note'],
            null === $row['guardian_id'] ? null : (string) $row['guardian_id'],
        ), $rows);
    }

    public function eventCount(string $id): int
    {
        return (int) $this->connection->fetchOne('SELECT count(*) FROM children_incident_events WHERE incident_id = :id', ['id' => $id]);
    }

    public function attended(string $childId, string $sessionId, string $segmentId, DateTimeImmutable $at): bool
    {
        return false !== $this->connection->fetchOne(
            'SELECT 1 FROM children_attendance_segments g JOIN children_attendance t ON t.id = g.attendance_id
             WHERE t.child_id = :child AND g.id = :segment AND g.session_id = :session AND g.arrived_at <= :at AND (g.left_at IS NULL OR g.left_at > :at) LIMIT 1',
            ['child' => $childId, 'segment' => $segmentId, 'session' => $sessionId, 'at' => Timestamps::toDatabase($at)],
        );
    }

    public function list(?string $sessionId, ?string $childId, string $status, bool $overdueOnly, CalendarDate $on, int $limit, int $offset): IncidentPage
    {
        $where = 'retain_until >= :on';
        $parameters = ['on' => $on->iso()];
        if (null !== $sessionId) {
            $where .= ' AND session_id = :session';
            $parameters['session'] = $sessionId;
        }
        if (null !== $childId) {
            $where .= ' AND child_id = :child';
            $parameters['child'] = $childId;
        }
        if ('ALL' !== $status) {
            if ('ACTIVE' === $status) {
                $where .= " AND status <> 'CLOSED'";
            } else {
                $where .= ' AND status = :status';
                $parameters['status'] = $status;
            }
        }
        $active = " AND status <> 'CLOSED'";
        $overdue = (int) $this->connection->fetchOne("SELECT count(*) FROM children_incidents WHERE $where$active AND review_on <= :on", $parameters);
        $unassigned = (int) $this->connection->fetchOne("SELECT count(*) FROM children_incidents WHERE $where$active AND owner_id IS NULL", $parameters);
        if ($overdueOnly) {
            $where .= "$active AND review_on <= :on";
        }
        $total = (int) $this->connection->fetchOne("SELECT count(*) FROM children_incidents WHERE $where", $parameters);
        $rows = $this->connection->fetchAllAssociative("SELECT * FROM children_incidents WHERE $where ORDER BY review_on, id LIMIT :limit OFFSET :offset", $parameters + ['limit' => $limit, 'offset' => $offset]);

        return new IncidentPage(array_map(self::summary(...), $rows), $total, $overdue, $unassigned);
    }

    public function visits(string $sessionId, ?array $matchingChildIds, int $limit, int $offset): IncidentVisits
    {
        if (null !== $matchingChildIds && [] === $matchingChildIds) {
            return new IncidentVisits([], 0);
        }
        $where = 'g.session_id = :session';
        $parameters = ['session' => $sessionId];
        $types = [];
        if (null !== $matchingChildIds) {
            $where .= ' AND t.child_id IN (:children)';
            $parameters['children'] = $matchingChildIds;
            $types['children'] = ArrayParameterType::STRING;
        }
        $from = 'children_attendance_segments g JOIN children_attendance t ON t.id = g.attendance_id';
        $total = (int) $this->connection->fetchOne("SELECT count(*) FROM $from WHERE $where", $parameters, $types);
        $rows = $this->connection->fetchAllAssociative("SELECT g.id, t.child_id, g.arrived_at, g.left_at FROM $from WHERE $where ORDER BY g.arrived_at DESC, g.id LIMIT :limit OFFSET :offset", $parameters + ['limit' => $limit, 'offset' => $offset], $types);

        return new IncidentVisits(array_map(static fn (array $row): IncidentVisit => new IncidentVisit(
            (string) $row['id'],
            (string) $row['child_id'],
            Timestamps::fromDatabase((string) $row['arrived_at']),
            null === $row['left_at'] ? null : Timestamps::fromDatabase((string) $row['left_at']),
        ), $rows), $total);
    }

    public function top(CalendarDate $on, int $limit): IncidentPage
    {
        return $this->list(null, null, 'ACTIVE', false, $on, $limit, 0);
    }

    public function unresolved(string $sessionId, CalendarDate $on): int
    {
        return (int) $this->connection->fetchOne('SELECT count(*) FROM children_incidents WHERE session_id = :session AND '.self::ACTIVE_IN_RETENTION, ['session' => $sessionId, 'on' => $on->iso()]);
    }

    public function counts(string $sessionId, CalendarDate $on): IncidentCountSummary
    {
        $row = $this->connection->fetchNumeric(
            'SELECT count(*), count(*) FILTER (WHERE review_on <= :on), count(*) FILTER (WHERE owner_id IS NULL) FROM children_incidents WHERE session_id = :session AND '.self::ACTIVE_IN_RETENTION,
            ['session' => $sessionId, 'on' => $on->iso()],
        );

        return new IncidentCountSummary((int) ($row[0] ?? 0), (int) ($row[1] ?? 0), (int) ($row[2] ?? 0));
    }

    /** @param array<string, mixed> $row */
    private static function incident(array $row): Incident
    {
        return Incident::reconstitute(
            (string) $row['id'],
            (string) $row['child_id'],
            (string) $row['session_id'],
            (string) $row['segment_id'],
            IncidentCategory::from((string) $row['category']),
            IncidentSeverity::from((string) $row['severity']),
            Timestamps::fromDatabase((string) $row['occurred_at']),
            (string) $row['reported_by'],
            Timestamps::fromDatabase((string) $row['reported_at']),
            IncidentStatus::from((string) $row['status']),
            null === $row['owner_id'] ? null : (string) $row['owner_id'],
            CalendarDate::parse((string) $row['review_on']),
            CalendarDate::parse((string) $row['retain_until']),
            (int) $row['version'],
        );
    }

    /** @param array<string, mixed> $row */
    private static function summary(array $row): IncidentSummary
    {
        return new IncidentSummary(
            (string) $row['id'],
            (string) $row['child_id'],
            (string) $row['session_id'],
            (string) $row['segment_id'],
            IncidentCategory::from((string) $row['category']),
            IncidentSeverity::from((string) $row['severity']),
            Timestamps::fromDatabase((string) $row['occurred_at']),
            (string) $row['reported_by'],
            Timestamps::fromDatabase((string) $row['reported_at']),
            IncidentStatus::from((string) $row['status']),
            null === $row['owner_id'] ? null : (string) $row['owner_id'],
            CalendarDate::parse((string) $row['review_on']),
            CalendarDate::parse((string) $row['retain_until']),
            (int) $row['version'],
        );
    }
}
