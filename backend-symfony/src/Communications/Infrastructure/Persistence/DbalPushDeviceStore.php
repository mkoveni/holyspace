<?php

declare(strict_types=1);

namespace App\Communications\Infrastructure\Persistence;

use App\Communications\Application\Contract\PushDeviceView;
use App\Communications\Application\Contract\PushPreference;
use App\Communications\Application\Contract\PushReader;
use App\Communications\Domain\CommunicationException;
use App\Communications\Domain\MemberNotice;
use App\Communications\Domain\PushDevice;
use App\Communications\Domain\Repository\PushDeviceRepository;
use App\Shared\Infrastructure\Persistence\Timestamps;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\ParameterType;

/** Device registrations and push topic preferences (V30). At most one ACTIVE registration holds a token. */
final readonly class DbalPushDeviceStore implements PushReader, PushDeviceRepository
{
    private const string DEVICES = 'communication_push_devices';

    public function __construct(private Connection $connection)
    {
    }

    public function ensure(PushDevice $draft): void
    {
        // Reinstalling on the same account retires that account's old installation for this token.
        $this->connection->createQueryBuilder()
            ->update(self::DEVICES)
            ->set('status', ':revoked')
            ->set('revision', 'revision + 1')
            ->where('token_hash = :token', 'account_id = :account', 'id <> :id', 'status = :active')
            ->setParameter('revoked', PushDevice::REVOKED)
            ->setParameter('active', PushDevice::ACTIVE)
            ->setParameter('token', $draft->tokenHash)
            ->setParameter('account', $draft->accountId)
            ->setParameter('id', $draft->id)
            ->executeStatement();
        $row = self::row($draft);
        try {
            // ON CONFLICT is not expressible with the query builder: an existing installation is renewed by save().
            $this->connection->executeStatement(
                'INSERT INTO '.self::DEVICES.' ('.implode(', ', array_keys($row)).') VALUES ('.implode(', ', array_fill(0, count($row), '?')).') ON CONFLICT (id) DO NOTHING',
                array_values($row),
                [9 => ParameterType::INTEGER],
            );
        } catch (UniqueConstraintViolationException) {
            throw CommunicationException::conflict('This push token is already registered. Sign out of the previous device registration first.');
        }
    }

    public function lock(string $id): ?PushDevice
    {
        $row = $this->connection->createQueryBuilder()
            ->select('*')
            ->from(self::DEVICES)
            ->where('id = :id')
            ->setParameter('id', $id)
            ->forUpdate()
            ->fetchAssociative();

        return false === $row ? null : self::device($row);
    }

    public function save(PushDevice $device): void
    {
        $update = $this->connection->createQueryBuilder()->update(self::DEVICES)->where('id = :id')->setParameter('id', $device->id);
        foreach (self::row($device) as $column => $value) {
            if ('id' !== $column) {
                $update->set($column, ':'.$column)->setParameter($column, $value, 'revision' === $column ? ParameterType::INTEGER : ParameterType::STRING);
            }
        }
        try {
            $update->executeStatement();
        } catch (UniqueConstraintViolationException) {
            throw CommunicationException::conflict('This push token is already registered to another installation.');
        }
    }

    public function devices(string $account, string $session): array
    {
        $rows = $this->connection->createQueryBuilder()
            ->select('id', 'platform', 'status', 'expires_at', 'updated_at', 'session_id')
            ->from(self::DEVICES)
            ->where('account_id = :account')
            ->setParameter('account', $account)
            ->orderBy('updated_at', 'DESC')
            ->addOrderBy('id')
            ->setMaxResults(100)
            ->fetchAllAssociative();

        return array_values(array_map(static fn (array $row): PushDeviceView => new PushDeviceView(
            (string) $row['id'],
            (string) $row['platform'],
            (string) $row['status'],
            Timestamps::fromDatabase((string) $row['expires_at']),
            Timestamps::fromDatabase((string) $row['updated_at']),
            $session === (string) $row['session_id'],
        ), $rows));
    }

    public function preferences(string $account): array
    {
        $stored = [];
        $rows = $this->connection->createQueryBuilder()
            ->select('topic', 'enabled', 'version')
            ->from('communication_push_preferences')
            ->where('account_id = :account')
            ->setParameter('account', $account)
            ->fetchAllAssociative();
        foreach ($rows as $row) {
            $stored[(string) $row['topic']] = new PushPreference((string) $row['topic'], (bool) $row['enabled'], (int) $row['version']);
        }

        // Push topics default off: a member opts in per topic.
        return array_map(static fn (string $topic): PushPreference => $stored[$topic] ?? new PushPreference($topic, false, 0), MemberNotice::TOPICS);
    }

    /** @return array<string, string|int> */
    private static function row(PushDevice $device): array
    {
        return [
            'id' => $device->id,
            'account_id' => $device->accountId,
            'session_id' => $device->sessionId,
            'secret_hash' => $device->secretHash,
            'token_hash' => $device->tokenHash,
            'encrypted_token' => $device->encryptedToken,
            'project_id' => $device->projectId,
            'platform' => $device->platform,
            'status' => $device->status,
            'revision' => $device->revision,
            'expires_at' => Timestamps::toDatabase($device->expiresAt),
            'updated_at' => Timestamps::toDatabase($device->updatedAt),
        ];
    }

    /** @param array<string, mixed> $row */
    private static function device(array $row): PushDevice
    {
        return PushDevice::reconstitute(
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
}
