<?php

declare(strict_types=1);

namespace App\IdentityAccess\Infrastructure\Persistence;

use App\IdentityAccess\Application\Contract\DeviceSession;
use App\IdentityAccess\Application\Contract\DeviceSessionReader;
use App\IdentityAccess\Application\Contract\SessionReader;
use App\IdentityAccess\Application\Contract\SessionRecord;
use App\IdentityAccess\Application\Contract\SessionWriter;
use App\Shared\Infrastructure\Persistence\Timestamps;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;

final readonly class DbalSessionStore implements SessionReader, SessionWriter, DeviceSessionReader
{
    public function __construct(private Connection $connection)
    {
    }

    public function find(string $tokenHash): ?SessionRecord
    {
        return $this->one('token_hash', $tokenHash);
    }

    public function findById(string $sessionId): ?SessionRecord
    {
        return $this->one('id', $sessionId);
    }

    public function create(
        string $id,
        string $accountId,
        string $tokenHash,
        DateTimeImmutable $expiresAt,
        DateTimeImmutable $now,
        string $deviceName,
    ): void {
        $this->connection->insert('account_sessions', [
            'id' => $id,
            'account_id' => $accountId,
            'token_hash' => $tokenHash,
            'expires_at' => Timestamps::toDatabase($expiresAt),
            'created_at' => Timestamps::toDatabase($now),
            'device_name' => $deviceName,
        ]);
    }

    public function revoke(string $sessionId, string $accountId): void
    {
        $this->connection->delete('account_sessions', ['id' => $sessionId, 'account_id' => $accountId]);
    }

    public function revokeAll(string $accountId): void
    {
        $this->connection->delete('account_sessions', ['account_id' => $accountId]);
    }

    public function devices(string $accountId, DateTimeImmutable $now, int $limit, int $offset): array
    {
        $rows = $this->connection->createQueryBuilder()
            ->select('id', 'device_name', 'created_at', 'expires_at')
            ->from('account_sessions')
            ->where('account_id = :account')
            ->andWhere('expires_at > :now')
            ->setParameter('account', $accountId)
            ->setParameter('now', Timestamps::toDatabase($now))
            ->orderBy('created_at', 'DESC')
            ->addOrderBy('id')
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->fetchAllAssociative();

        return array_values(array_map(static fn (array $row): DeviceSession => new DeviceSession(
            (string) $row['id'],
            (string) $row['device_name'],
            Timestamps::fromDatabase((string) $row['created_at']),
            Timestamps::fromDatabase((string) $row['expires_at']),
        ), $rows));
    }

    private function one(string $column, string $value): ?SessionRecord
    {
        $row = $this->connection->createQueryBuilder()
            ->select('id', 'account_id', 'expires_at')->from('account_sessions')
            ->where($column.' = :value')->setParameter('value', $value)
            ->fetchAssociative();

        return false === $row ? null : new SessionRecord(
            (string) $row['id'],
            (string) $row['account_id'],
            Timestamps::fromDatabase((string) $row['expires_at']),
        );
    }
}
