<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Persistence;

use Doctrine\DBAL\Connection;
use LogicException;

/**
 * Transaction-scoped PostgreSQL advisory locks. They are released on commit or rollback,
 * so they must only be taken inside a UnitOfWork.
 */
final readonly class AdvisoryLock
{
    public function __construct(private Connection $connection)
    {
    }

    public function lock(int $key): void
    {
        $this->assertInTransaction();
        $this->connection->fetchOne('SELECT pg_advisory_xact_lock(?)', [$key]);
    }

    /** Locks on a text key, hashed the same way as the Java application (hashtextextended, seed 0). */
    public function lockKey(string $key): void
    {
        $this->assertInTransaction();
        $this->connection->fetchOne('SELECT pg_advisory_xact_lock(hashtextextended(?, 0))', [$key]);
    }

    private function assertInTransaction(): void
    {
        if (!$this->connection->isTransactionActive()) {
            throw new LogicException('Advisory locks are transaction-scoped and require an active UnitOfWork.');
        }
    }
}
