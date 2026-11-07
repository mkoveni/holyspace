<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Sessions\Infrastructure\Persistence;

use App\ChildrenMinistry\Sessions\Application\Contract\SessionReader;
use App\ChildrenMinistry\Sessions\Domain\MinistrySession;
use App\ChildrenMinistry\Sessions\Domain\Repository\SessionRepository;
use App\ChildrenMinistry\Sessions\Domain\SessionStaff;
use App\ChildrenMinistry\Sessions\Domain\SessionStatus;
use App\ChildrenMinistry\Sessions\Domain\StaffRole;
use App\ChildrenMinistry\Sessions\Domain\Worker;
use App\ChildrenMinistry\Shared\Domain\ChildrenException;
use App\Shared\Infrastructure\Persistence\Timestamps;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;

final readonly class DbalSessionStore implements SessionRepository, SessionReader
{
    private const string SESSIONS = 'children_sessions';

    public function __construct(private Connection $connection)
    {
    }

    public function ministrySession(string $id): ?MinistrySession
    {
        return $this->sessionRow($id, false);
    }

    public function ministrySessionForUpdate(string $id): ?MinistrySession
    {
        return $this->sessionRow($id, true);
    }

    public function insertMinistrySession(MinistrySession $session): void
    {
        $this->connection->insert(self::SESSIONS, ['id' => $session->id] + $this->columns($session));
    }

    public function updateMinistrySession(MinistrySession $session): void
    {
        $changed = $this->connection->update(self::SESSIONS, $this->columns($session), ['id' => $session->id, 'version' => $session->version - 1]);
        if (1 !== $changed) {
            throw ChildrenException::conflict('Record changed. Reload and try again.');
        }
    }

    public function sessions(?string $locationId, DateTimeImmutable $from, DateTimeImmutable $until, ?int $limit, int $offset): array
    {
        $query = $this->connection->createQueryBuilder()
            ->select('s.*')
            ->from(self::SESSIONS, 's')
            ->where('s.starts_at < :until')
            ->andWhere('s.ends_at > :from')
            ->setParameter('until', Timestamps::toDatabase($until))
            ->setParameter('from', Timestamps::toDatabase($from));
        if (null !== $locationId) {
            $query->join('s', 'children_rooms', 'r', 'r.id = s.room_id')->andWhere('r.location_id = :location')->setParameter('location', $locationId);
        }

        return $this->page($query, $limit, $offset);
    }

    public function stationSessions(string $accountId, DateTimeImmutable $from, DateTimeImmutable $until, int $limit, int $offset): array
    {
        $query = $this->connection->createQueryBuilder()
            ->select('s.*')
            ->from(self::SESSIONS, 's')
            ->join('s', 'children_session_staff', 'f', 'f.session_id = s.id')
            ->join('f', 'children_workers', 'w', 'w.person_id = f.worker_id')
            ->where('w.account_id = :account')
            ->andWhere('w.revoked_at IS NULL')
            ->andWhere('f.present = TRUE')
            ->andWhere('s.starts_at < :until')
            ->andWhere('s.ends_at > :from')
            ->setParameter('account', $accountId)
            ->setParameter('until', Timestamps::toDatabase($until))
            ->setParameter('from', Timestamps::toDatabase($from));

        return $this->page($query, $limit, $offset);
    }

    public function staff(string $sessionId): array
    {
        $rows = $this->connection->createQueryBuilder()
            ->select('*')
            ->from('children_session_staff')
            ->where('session_id = :session')
            ->setParameter('session', $sessionId)
            ->orderBy('worker_id')
            ->fetchAllAssociative();

        return array_values(array_map(static fn (array $row): SessionStaff => new SessionStaff((string) $row['session_id'], (string) $row['worker_id'], StaffRole::from((string) $row['role']), (bool) $row['present']), $rows));
    }

    public function worker(string $personId): ?Worker
    {
        return $this->workerRow('person_id', $personId);
    }

    public function workerByAccount(string $accountId): ?Worker
    {
        return $this->workerRow('account_id', $accountId);
    }

    public function presentElsewhere(string $workerId, string $sessionId, DateTimeImmutable $from, DateTimeImmutable $until): bool
    {
        return false !== $this->connection->fetchOne(
            "SELECT 1 FROM children_session_staff f JOIN children_sessions s ON s.id = f.session_id WHERE f.worker_id = :worker AND f.present = TRUE AND s.id <> :session AND s.status NOT IN ('CLOSED', 'CANCELLED') AND s.starts_at < :until AND s.ends_at > :from LIMIT 1",
            ['worker' => $workerId, 'session' => $sessionId, 'until' => Timestamps::toDatabase($until), 'from' => Timestamps::toDatabase($from)],
        );
    }

    public function workerForUpdate(string $personId): ?Worker
    {
        return $this->workerRow('person_id', $personId, true);
    }

    public function insertWorker(Worker $worker): void
    {
        $this->connection->insert('children_workers', ['person_id' => $worker->personId] + $this->workerColumns($worker));
    }

    public function updateWorker(Worker $worker): void
    {
        $changed = $this->connection->update('children_workers', $this->workerColumns($worker), ['person_id' => $worker->personId, 'version' => $worker->version - 1]);
        if (1 !== $changed) {
            throw ChildrenException::conflict('Record changed. Reload and try again.');
        }
    }

    public function saveSessionStaff(SessionStaff $staff): void
    {
        $this->connection->executeStatement(
            'INSERT INTO children_session_staff (session_id, worker_id, role, present) VALUES (:session, :worker, :role, :present) ON CONFLICT (session_id, worker_id) DO UPDATE SET role = EXCLUDED.role, present = EXCLUDED.present',
            ['session' => $staff->sessionId, 'worker' => $staff->workerId, 'role' => $staff->role->value, 'present' => $staff->present],
            ['present' => \Doctrine\DBAL\ParameterType::BOOLEAN],
        );
    }

    /** @return array<string, mixed> */
    private function workerColumns(Worker $worker): array
    {
        return [
            'account_id' => $worker->accountId,
            'verified_from' => Timestamps::toDatabase($worker->verifiedFrom),
            'screened_until' => Timestamps::toDatabase($worker->screenedUntil),
            'trained_until' => Timestamps::toDatabase($worker->trainedUntil),
            'evidence_reference' => $worker->evidenceReference,
            'verified_by' => $worker->verifiedBy,
            'revoked_at' => null === $worker->revokedAt ? null : Timestamps::toDatabase($worker->revokedAt),
            'version' => $worker->version,
        ];
    }

    /** @return list<MinistrySession> */
    private function page(QueryBuilder $query, ?int $limit, int $offset): array
    {
        $query->orderBy('s.starts_at')->addOrderBy('s.id')->setFirstResult($offset);
        if (null !== $limit) {
            $query->setMaxResults($limit);
        }

        return array_values(array_map(self::session(...), $query->fetchAllAssociative()));
    }

    private function sessionRow(string $id, bool $lock): ?MinistrySession
    {
        $query = $this->connection->createQueryBuilder()->select('*')->from(self::SESSIONS)->where('id = :id')->setParameter('id', $id);
        if ($lock) {
            $query->forUpdate();
        }
        $row = $query->fetchAssociative();

        return false === $row ? null : self::session($row);
    }

    private function workerRow(string $column, string $value, bool $lock = false): ?Worker
    {
        $query = $this->connection->createQueryBuilder()->select('*')->from('children_workers')->where($column.' = :value')->setParameter('value', $value);
        if ($lock) {
            $query->forUpdate();
        }
        $row = $query->fetchAssociative();
        if (false === $row) {
            return null;
        }

        return self::workerFrom($row);
    }

    /** @param array<string, mixed> $row */
    public static function workerFrom(array $row): Worker
    {
        return Worker::reconstitute(
            (string) $row['person_id'],
            (string) $row['account_id'],
            Timestamps::fromDatabase((string) $row['verified_from']),
            Timestamps::fromDatabase((string) $row['screened_until']),
            Timestamps::fromDatabase((string) $row['trained_until']),
            (string) $row['evidence_reference'],
            (string) $row['verified_by'],
            null === $row['revoked_at'] ? null : Timestamps::fromDatabase((string) $row['revoked_at']),
            (int) $row['version'],
        );
    }

    /** @param array<string, mixed> $row */
    public static function session(array $row): MinistrySession
    {
        return MinistrySession::reconstitute(
            (string) $row['id'],
            (string) $row['class_id'],
            (string) $row['room_id'],
            Timestamps::fromDatabase((string) $row['starts_at']),
            Timestamps::fromDatabase((string) $row['ends_at']),
            (int) $row['seat_limit'],
            SessionStatus::from((string) $row['status']),
            (int) $row['version'],
        );
    }

    /** @return array<string, mixed> */
    private function columns(MinistrySession $session): array
    {
        return [
            'class_id' => $session->classId,
            'room_id' => $session->roomId,
            'starts_at' => Timestamps::toDatabase($session->startsAt),
            'ends_at' => Timestamps::toDatabase($session->endsAt),
            'seat_limit' => $session->seatLimit,
            'status' => $session->status->value,
            'version' => $session->version,
        ];
    }
}
