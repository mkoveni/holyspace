<?php

declare(strict_types=1);

namespace App\Communications\Infrastructure\Persistence;

use App\Communications\Application\Contract\PushWriter;
use App\Communications\Domain\CommunicationException;
use App\Communications\Domain\PushDeliveryPolicy;
use App\Communications\Domain\PushDevice;
use App\Communications\Domain\PushJob;
use App\Communications\Domain\Repository\PushQueue;
use App\Shared\Application\IdGenerator;
use App\Shared\Infrastructure\Persistence\Timestamps;
use DateTimeImmutable;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Query\ForUpdate\ConflictResolutionMode;
use Psr\Clock\ClockInterface;

/** Push topic preferences and the device push outbox (V30). */
final readonly class DbalPushQueueStore implements PushWriter, PushQueue
{
    private const string PREFERENCES = 'communication_push_preferences';
    private const string DELIVERIES = 'communication_push_deliveries';
    private const string DEVICES = 'communication_push_devices';
    private const int BATCH = 25;
    private const int FAN_OUT = 100;
    private const int RECOVERY_BATCH = 1000;

    public function __construct(private Connection $connection, private IdGenerator $ids, private ClockInterface $clock)
    {
    }

    public function preference(string $account, string $topic, bool $enabled, int $expectedVersion): void
    {
        // ON CONFLICT is not expressible with the query builder: create the default (off) row, then lock it.
        $this->connection->executeStatement(
            'INSERT INTO '.self::PREFERENCES.' (account_id, topic) VALUES (?, ?) ON CONFLICT DO NOTHING',
            [$account, $topic],
        );
        $version = (int) $this->connection->createQueryBuilder()
            ->select('version')
            ->from(self::PREFERENCES)
            ->where('account_id = :account', 'topic = :topic')
            ->setParameter('account', $account)
            ->setParameter('topic', $topic)
            ->forUpdate()
            ->fetchOne();
        if ($version !== $expectedVersion) {
            throw CommunicationException::conflict('Push preferences changed. Refresh and try again.');
        }
        $this->connection->createQueryBuilder()
            ->update(self::PREFERENCES)
            ->set('enabled', ':enabled')
            ->set('version', ':version')
            ->where('account_id = :account', 'topic = :topic')
            ->setParameter('enabled', $enabled, ParameterType::BOOLEAN)
            ->setParameter('version', $version + 1, ParameterType::INTEGER)
            ->setParameter('account', $account)
            ->setParameter('topic', $topic)
            ->executeStatement();
    }

    public function enqueue(string $notification, string $account, string $topic): void
    {
        $now = Timestamps::toDatabase($this->clock->now());
        $targets = $this->connection->createQueryBuilder()
            ->select('d.id', 'd.revision', 'p.version')
            ->from(self::DEVICES, 'd')
            ->innerJoin('d', self::PREFERENCES, 'p', 'p.account_id = d.account_id')
            ->where('d.account_id = :account', 'd.status = :active', 'd.expires_at > :now', 'p.topic = :topic', 'p.enabled')
            ->setParameter('account', $account)
            ->setParameter('active', PushDevice::ACTIVE)
            ->setParameter('now', $now)
            ->setParameter('topic', $topic)
            ->setMaxResults(self::FAN_OUT)
            ->fetchAllAssociative();
        foreach ($targets as $target) {
            // ON CONFLICT is not expressible with the query builder: one job per notice and device.
            $this->connection->executeStatement(
                'INSERT INTO '.self::DELIVERIES.' (id, notification_id, device_id, device_revision, topic, preference_version, status, due_at, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?) ON CONFLICT (notification_id, device_id) DO NOTHING',
                [$this->ids->uuid(), $notification, (string) $target['id'], (int) $target['revision'], $topic, (int) $target['version'], PushJob::QUEUED, $now, $now],
                [3 => ParameterType::INTEGER, 5 => ParameterType::INTEGER],
            );
        }
    }

    public function due(DateTimeImmutable $at): array
    {
        return array_values(array_map('strval', $this->connection->createQueryBuilder()
            ->select('id')
            ->from(self::DELIVERIES)
            ->where('status IN (:waiting)', 'due_at <= :at')
            ->setParameter('waiting', [PushJob::QUEUED, PushJob::RETRY, PushJob::TICKET], ArrayParameterType::STRING)
            ->setParameter('at', Timestamps::toDatabase($at))
            ->orderBy('due_at')
            ->addOrderBy('id')
            ->setMaxResults(self::BATCH)
            ->fetchFirstColumn()));
    }

    public function claim(string $id, DateTimeImmutable $at): ?PushJob
    {
        $row = $this->connection->createQueryBuilder()
            ->select('*')
            ->from(self::DELIVERIES)
            ->where('id = :id', 'status IN (:waiting)', 'due_at <= :at')
            ->setParameter('id', $id)
            ->setParameter('waiting', [PushJob::QUEUED, PushJob::RETRY, PushJob::TICKET], ArrayParameterType::STRING)
            ->setParameter('at', Timestamps::toDatabase($at))
            ->forUpdate(ConflictResolutionMode::SKIP_LOCKED)
            ->fetchAssociative();
        if (false === $row) {
            return null;
        }
        $receipt = PushJob::TICKET === $row['status'];
        $row['status'] = $receipt ? PushJob::CHECKING : PushJob::SENDING;
        $row['claim_id'] = $this->ids->uuid();
        $row['attempts'] = (int) $row['attempts'] + ($receipt ? 0 : 1);
        $this->connection->createQueryBuilder()
            ->update(self::DELIVERIES)
            ->set('status', ':status')
            ->set('claim_id', ':claim')
            ->set('lease_until', ':lease')
            ->set('attempts', ':attempts')
            ->where('id = :id')
            ->setParameter('status', $row['status'])
            ->setParameter('claim', $row['claim_id'])
            ->setParameter('lease', Timestamps::toDatabase($at->modify('+'.PushDeliveryPolicy::LEASE_SECONDS.' seconds')))
            ->setParameter('attempts', $row['attempts'], ParameterType::INTEGER)
            ->setParameter('id', $id)
            ->executeStatement();

        return new PushJob(
            (string) $row['id'],
            (string) $row['notification_id'],
            (string) $row['device_id'],
            (int) $row['device_revision'],
            (string) $row['topic'],
            (int) $row['preference_version'],
            (string) $row['status'],
            (int) $row['attempts'],
            Timestamps::fromDatabase((string) $row['created_at']),
            (string) $row['claim_id'],
            null === $row['ticket_id'] ? null : (string) $row['ticket_id'],
        );
    }

    public function eligible(PushJob $job, DateTimeImmutable $at): ?PushDevice
    {
        $row = $this->connection->createQueryBuilder()
            ->select('d.*')
            ->from(self::DEVICES, 'd')
            ->innerJoin('d', self::PREFERENCES, 'p', 'p.account_id = d.account_id')
            ->innerJoin('d', 'communication_member_notifications', 'n', 'n.account_id = d.account_id')
            ->where('d.id = :device', 'd.revision = :revision', 'd.status = :active', 'd.expires_at > :at')
            ->andWhere('n.id = :notification', 'p.topic = :topic', 'p.enabled', 'p.version = :version')
            ->setParameter('device', $job->deviceId)
            ->setParameter('revision', $job->deviceRevision, ParameterType::INTEGER)
            ->setParameter('active', PushDevice::ACTIVE)
            ->setParameter('at', Timestamps::toDatabase($at))
            ->setParameter('notification', $job->notificationId)
            ->setParameter('topic', $job->topic)
            ->setParameter('version', $job->preferenceVersion, ParameterType::INTEGER)
            ->fetchAssociative();

        return false === $row ? null : PushDevice::reconstitute(
            (string) $row['id'],
            (string) $row['account_id'],
            (string) $row['session_id'],
            (string) $row['secret_hash'],
            (string) $row['token_hash'],
            (string) $row['encrypted_token'],
            (string) $row['project_id'],
            (string) $row['platform'],
            (string) $row['status'],
            (int) $row['revision'],
            Timestamps::fromDatabase((string) $row['expires_at']),
            Timestamps::fromDatabase((string) $row['updated_at']),
        );
    }

    public function finish(PushJob $job, string $state, DateTimeImmutable $due, ?string $ticket, string $error, DateTimeImmutable $at): void
    {
        $changed = (int) $this->connection->createQueryBuilder()
            ->update(self::DELIVERIES)
            ->set('status', ':state')
            ->set('due_at', ':due')
            ->set('ticket_id', ':ticket')
            ->set('error_code', ':error')
            ->set('lease_until', 'NULL')
            ->where('id = :id', 'claim_id = :claim', 'status IN (:claimed)')
            ->setParameter('state', $state)
            ->setParameter('due', Timestamps::toDatabase($due))
            ->setParameter('ticket', $ticket)
            ->setParameter('error', $error)
            ->setParameter('id', $job->id)
            ->setParameter('claim', $job->claimId)
            ->setParameter('claimed', [PushJob::SENDING, PushJob::CHECKING], ArrayParameterType::STRING)
            ->executeStatement();
        if ($changed > 0 && 'DeviceNotRegistered' === $error) {
            // Only the revision this job was aimed at: a device re-registered since then stays active.
            $this->connection->createQueryBuilder()
                ->update(self::DEVICES)
                ->set('status', ':invalid')
                ->set('revision', 'revision + 1')
                ->where('id = :id', 'revision = :revision')
                ->setParameter('invalid', PushDevice::INVALID)
                ->setParameter('id', $job->deviceId)
                ->setParameter('revision', $job->deviceRevision, ParameterType::INTEGER)
                ->executeStatement();
        }
    }

    public function recover(DateTimeImmutable $at): void
    {
        $now = Timestamps::toDatabase($at);
        $this->expiredClaims(PushJob::SENDING, $now, ['status' => PushJob::UNKNOWN, 'error_code' => 'LEASE_EXPIRED']);
        $this->expiredClaims(PushJob::CHECKING, $now, ['status' => PushJob::TICKET, 'due_at' => $now]);

        $leased = $this->connection->createQueryBuilder()
            ->select('id')
            ->from(self::DEVICES)
            ->where('status = :active', 'expires_at <= :at')
            ->setParameter('active', PushDevice::ACTIVE)
            ->setParameter('at', $now)
            ->setMaxResults(self::RECOVERY_BATCH)
            ->fetchFirstColumn();
        if ([] !== $leased) {
            $this->connection->createQueryBuilder()
                ->update(self::DEVICES)
                ->set('status', ':revoked')
                ->set('revision', 'revision + 1')
                ->where('id IN (:ids)')
                ->setParameter('revoked', PushDevice::REVOKED)
                ->setParameter('ids', $leased, ArrayParameterType::STRING)
                ->executeStatement();
        }
    }

    /** @param array<string, string> $changes */
    private function expiredClaims(string $status, string $now, array $changes): void
    {
        $ids = $this->connection->createQueryBuilder()
            ->select('id')
            ->from(self::DELIVERIES)
            ->where('status = :status', 'lease_until < :at')
            ->setParameter('status', $status)
            ->setParameter('at', $now)
            ->setMaxResults(self::RECOVERY_BATCH)
            ->fetchFirstColumn();
        if ([] === $ids) {
            return;
        }
        $update = $this->connection->createQueryBuilder()
            ->update(self::DELIVERIES)
            ->where('id IN (:ids)', 'status = :status')
            ->setParameter('ids', $ids, ArrayParameterType::STRING)
            ->setParameter('status', $status);
        foreach ($changes as $column => $value) {
            $update->set($column, ':set_'.$column)->setParameter('set_'.$column, $value);
        }
        $update->executeStatement();
    }
}
