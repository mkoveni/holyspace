<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Persistence;

use Doctrine\DBAL\Connection;
use Throwable;

/**
 * Runs several reads against one snapshot (REPEATABLE READ), so totals and the page they summarise agree. Inside an
 * existing transaction it simply runs, because the isolation level can only be chosen when a transaction starts.
 */
final class ConsistentRead
{
    private function __construct()
    {
    }

    /**
     * @template T
     *
     * @param callable(): T $reads
     *
     * @return T
     */
    public static function run(Connection $connection, callable $reads): mixed
    {
        if ($connection->isTransactionActive()) {
            return $reads();
        }
        $connection->beginTransaction();
        try {
            $connection->executeStatement('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
            $result = $reads();
            $connection->commit();

            return $result;
        } catch (Throwable $failure) {
            $connection->rollBack();

            throw $failure;
        }
    }
}
