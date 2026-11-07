<?php

declare(strict_types=1);

namespace App\IdentityAccess\Infrastructure\Persistence;

use App\IdentityAccess\Application\Contract\LoginAttemptWriter;
use App\Shared\Infrastructure\Persistence\AdvisoryLock;
use App\Shared\Infrastructure\Persistence\Timestamps;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;

final readonly class DbalLoginAttemptWriter implements LoginAttemptWriter
{
    private const int MAX_FAILURES = 5;
    private const int WINDOW_SECONDS = 60;

    public function __construct(private Connection $connection, private AdvisoryLock $locks)
    {
    }

    public function lockLogin(string $identifierHash): void
    {
        $this->locks->lockKey($identifierHash);
    }

    public function throttled(string $identifierHash, DateTimeImmutable $now): bool
    {
        return false !== $this->connection->createQueryBuilder()
            ->select('1')->from('login_attempts')
            ->where('identifier_hash = :key')->andWhere('failures >= :max')->andWhere('resets_at > :now')
            ->setParameter('key', $identifierHash)->setParameter('max', self::MAX_FAILURES)
            ->setParameter('now', Timestamps::toDatabase($now))
            ->fetchOne();
    }

    public function failed(string $identifierHash, DateTimeImmutable $now): void
    {
        $current = Timestamps::toDatabase($now);
        $resetsAt = Timestamps::toDatabase($now->modify(sprintf('+%d seconds', self::WINDOW_SECONDS)));

        // One atomic upsert: an expired window restarts at one failure, otherwise the counter increments.
        $this->connection->executeStatement(
            'INSERT INTO login_attempts (identifier_hash, failures, resets_at) '
            .'VALUES (:key, 1, CAST(:resets AS timestamptz)) '
            .'ON CONFLICT (identifier_hash) DO UPDATE SET '
            .'failures = CASE WHEN login_attempts.resets_at <= CAST(:now AS timestamptz) THEN 1 ELSE login_attempts.failures + 1 END, '
            .'resets_at = CASE WHEN login_attempts.resets_at <= CAST(:now AS timestamptz) THEN CAST(:resets AS timestamptz) ELSE login_attempts.resets_at END',
            ['key' => $identifierHash, 'resets' => $resetsAt, 'now' => $current],
        );
    }

    public function clearFailures(string $identifierHash): void
    {
        $this->connection->delete('login_attempts', ['identifier_hash' => $identifierHash]);
    }
}
