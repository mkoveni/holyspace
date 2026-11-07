<?php

declare(strict_types=1);

namespace App\Events\Infrastructure\Persistence;

use App\Events\Domain\AdmissionPass;
use App\Events\Domain\Repository\AdmissionPassRepository;
use App\Shared\Infrastructure\Persistence\Timestamps;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;

final readonly class DbalAdmissionPassStore implements AdmissionPassRepository
{
    public function __construct(private Connection $connection)
    {
    }

    public function insert(AdmissionPass $pass): void
    {
        $this->connection->insert('event_admission_passes', [
            'token_hash' => $pass->tokenHash,
            'registration_id' => $pass->registrationId,
            'account_id' => $pass->accountId,
            'session_id' => $pass->sessionId,
            'member_version' => $pass->memberVersion,
            'registration_version' => $pass->registrationVersion,
            'schedule_revision' => $pass->scheduleRevision,
            'expires_at' => Timestamps::toDatabase($pass->expiresAt),
        ]);
    }

    public function find(string $tokenHash): ?AdmissionPass
    {
        return $this->load('SELECT * FROM event_admission_passes WHERE token_hash = ?', $tokenHash);
    }

    public function lock(string $tokenHash): ?AdmissionPass
    {
        return $this->load('SELECT * FROM event_admission_passes WHERE token_hash = ? FOR UPDATE', $tokenHash);
    }

    public function markUsed(string $tokenHash, DateTimeImmutable $at): void
    {
        $this->connection->update('event_admission_passes', ['used_at' => Timestamps::toDatabase($at)], ['token_hash' => $tokenHash]);
    }

    private function load(string $sql, string $tokenHash): ?AdmissionPass
    {
        $row = $this->connection->fetchAssociative($sql, [$tokenHash]);

        return false === $row ? null : new AdmissionPass(
            (string) $row['token_hash'],
            (string) $row['registration_id'],
            (string) $row['account_id'],
            (string) $row['session_id'],
            (int) $row['member_version'],
            (int) $row['registration_version'],
            (int) $row['schedule_revision'],
            Timestamps::fromDatabase((string) $row['expires_at']),
            null === $row['used_at'] ? null : Timestamps::fromDatabase((string) $row['used_at']),
        );
    }
}
