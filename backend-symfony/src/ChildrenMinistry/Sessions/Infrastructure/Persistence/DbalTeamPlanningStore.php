<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Sessions\Infrastructure\Persistence;

use App\ChildrenMinistry\Sessions\Application\Contract\TeamPlanningReader;
use App\ChildrenMinistry\Sessions\Application\PlanningBooking;
use App\ChildrenMinistry\Sessions\Application\PlanningFacts;
use App\ChildrenMinistry\Sessions\Application\PlanningSession;
use App\ChildrenMinistry\Sessions\Application\PlanningWorkerPage;
use App\ChildrenMinistry\Sessions\Domain\Repository\TeamAssignmentRepository;
use App\ChildrenMinistry\Sessions\Domain\SessionStaff;
use App\ChildrenMinistry\Sessions\Domain\StaffRole;
use App\Shared\Infrastructure\Persistence\Timestamps;
use DateTimeImmutable;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;

final readonly class DbalTeamPlanningStore implements TeamPlanningReader, TeamAssignmentRepository
{
    private const string SELECT = 's.*, p.id AS programme_id, p.name AS programme_name, c.name AS class_name, r.name AS room_name, r.location_id, l.time_zone, r.capacity AS room_capacity, p.min_staff, p.children_per_staff';

    private const string FROM = 'children_sessions s JOIN children_classes c ON c.id = s.class_id JOIN children_programmes p ON p.id = c.programme_id JOIN children_rooms r ON r.id = s.room_id JOIN children_locations l ON l.id = r.location_id';

    public function __construct(private Connection $connection)
    {
    }

    public function upcoming(DateTimeImmutable $from, DateTimeImmutable $until, ?string $programmeId, ?string $locationId, DateTimeImmutable $now): array
    {
        $where = "s.starts_at >= :from AND s.starts_at < :until AND s.starts_at > :now AND s.status NOT IN ('CLOSED', 'CANCELLED')";
        $parameters = ['from' => Timestamps::toDatabase($from), 'until' => Timestamps::toDatabase($until), 'now' => Timestamps::toDatabase($now), 'limit' => 1001];
        if (null !== $programmeId) {
            $where .= ' AND p.id = :programme';
            $parameters['programme'] = $programmeId;
        }
        if (null !== $locationId) {
            $where .= ' AND r.location_id = :location';
            $parameters['location'] = $locationId;
        }

        return $this->sessionsWhere($where, $parameters);
    }

    public function sessions(array $ids): array
    {
        if ([] === $ids) {
            return [];
        }

        return $this->sessionsWhere('s.id IN (:ids)', ['ids' => $ids, 'limit' => 25], ['ids' => ArrayParameterType::STRING]);
    }

    public function facts(array $sessions, array $extraWorkers): PlanningFacts
    {
        if ([] === $sessions) {
            return new PlanningFacts([], [], []);
        }
        $ids = array_map(static fn (PlanningSession $row): string => $row->value->id, $sessions);
        $staffRows = $this->connection->fetchAllAssociative('SELECT session_id, worker_id, role, present FROM children_session_staff WHERE session_id IN (:ids)', ['ids' => $ids], ['ids' => ArrayParameterType::STRING]);
        $staff = array_map(static fn (array $row): SessionStaff => new SessionStaff((string) $row['session_id'], (string) $row['worker_id'], StaffRole::from((string) $row['role']), (bool) $row['present']), $staffRows);
        $workerIds = array_values(array_unique([...$extraWorkers, ...array_map(static fn (SessionStaff $member): string => $member->workerId, $staff)]));

        $workers = [];
        $bookings = [];
        if ([] !== $workerIds) {
            $rows = $this->connection->fetchAllAssociative('SELECT * FROM children_workers WHERE person_id IN (:ids)', ['ids' => $workerIds], ['ids' => ArrayParameterType::STRING]);
            $workers = array_map(DbalSessionStore::workerFrom(...), $rows);
            $from = min(array_map(static fn (PlanningSession $row): DateTimeImmutable => $row->value->startsAt, $sessions));
            $until = max(array_map(static fn (PlanningSession $row): DateTimeImmutable => $row->value->endsAt, $sessions));
            $rows = $this->connection->fetchAllAssociative(
                "SELECT f.session_id, f.worker_id, x.starts_at, x.ends_at FROM children_session_staff f JOIN children_sessions x ON x.id = f.session_id
                 WHERE f.worker_id IN (:workers) AND x.status NOT IN ('CLOSED', 'CANCELLED') AND x.starts_at < :until AND x.ends_at > :from",
                ['workers' => $workerIds, 'until' => Timestamps::toDatabase($until), 'from' => Timestamps::toDatabase($from)],
                ['workers' => ArrayParameterType::STRING],
            );
            $bookings = array_map(static fn (array $row): PlanningBooking => new PlanningBooking((string) $row['session_id'], (string) $row['worker_id'], Timestamps::fromDatabase((string) $row['starts_at']), Timestamps::fromDatabase((string) $row['ends_at'])), $rows);
        }

        return new PlanningFacts($staff, $workers, $bookings);
    }

    public function workers(?array $matchingIds, int $limit, int $offset): PlanningWorkerPage
    {
        if (null !== $matchingIds && [] === $matchingIds) {
            return new PlanningWorkerPage([], 0);
        }
        $where = null === $matchingIds ? 'TRUE' : 'person_id IN (:ids)';
        $parameters = null === $matchingIds ? [] : ['ids' => $matchingIds];
        $types = null === $matchingIds ? [] : ['ids' => ArrayParameterType::STRING];
        $rows = $this->connection->fetchAllAssociative("SELECT * FROM children_workers WHERE $where ORDER BY person_id LIMIT :limit OFFSET :offset", $parameters + ['limit' => $limit, 'offset' => $offset], $types);

        return new PlanningWorkerPage(array_map(DbalSessionStore::workerFrom(...), $rows), (int) $this->connection->fetchOne("SELECT count(*) FROM children_workers WHERE $where", $parameters, $types));
    }

    public function removeAssignment(string $sessionId, string $workerId): void
    {
        $this->connection->delete('children_session_staff', ['session_id' => $sessionId, 'worker_id' => $workerId]);
    }

    /**
     * @param array<string, mixed> $parameters
     * @param array<string, mixed> $types
     *
     * @return list<PlanningSession>
     */
    private function sessionsWhere(string $where, array $parameters, array $types = []): array
    {
        $rows = $this->connection->fetchAllAssociative('SELECT '.self::SELECT.' FROM '.self::FROM.' WHERE '.$where.' ORDER BY s.starts_at, s.id LIMIT :limit', $parameters, $types);

        return array_map(static fn (array $row): PlanningSession => new PlanningSession(
            DbalSessionStore::session($row),
            (string) $row['programme_id'],
            (string) $row['programme_name'],
            (string) $row['class_name'],
            (string) $row['room_name'],
            (string) $row['location_id'],
            (string) $row['time_zone'],
            (int) $row['room_capacity'],
            (int) $row['min_staff'],
            (int) $row['children_per_staff'],
        ), $rows);
    }
}
