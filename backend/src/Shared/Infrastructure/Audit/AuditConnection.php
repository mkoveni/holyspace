<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Audit;

use Doctrine\DBAL\Driver\Connection;
use Doctrine\DBAL\Driver\Middleware\AbstractConnectionMiddleware;
use Doctrine\DBAL\Driver\Result;
use Doctrine\DBAL\Driver\Statement;
use Symfony\Component\DependencyInjection\Attribute\Exclude;
use Throwable;

/** Adds transaction-local attribution without widening application transaction boundaries. */
#[Exclude]
final class AuditConnection extends AbstractConnectionMiddleware
{
    private bool $inTransaction = false;

    public function __construct(Connection $connection, private readonly AuditIdentity $identity)
    {
        parent::__construct($connection);
    }

    public function beginTransaction(): void
    {
        parent::beginTransaction();
        $this->inTransaction = true;
    }

    public function commit(): void
    {
        parent::commit();
        $this->inTransaction = false;
    }

    public function rollBack(): void
    {
        parent::rollBack();
        $this->inTransaction = false;
    }

    public function attributed(callable $operation): mixed
    {
        $ownsTransaction = !$this->inTransaction;
        if ($ownsTransaction) {
            $this->beginTransaction();
        }
        try {
            $statement = parent::prepare("SELECT set_config('TimeZone', 'UTC', true), set_config('app.actor_id', ?, true), set_config('app.request_id', ?, true), set_config('app.source', ?, true)");
            foreach ($this->identity->values() as $index => $value) {
                $statement->bindValue($index + 1, $value, \Doctrine\DBAL\ParameterType::STRING);
            }
            $statement->execute()->free();
            $result = $operation();
            if ($ownsTransaction) {
                $this->commit();
            }

            return $result;
        } catch (Throwable $error) {
            if ($ownsTransaction && $this->inTransaction) {
                $this->rollBack();
            }
            throw $error;
        }
    }

    public function prepare(string $sql): Statement
    {
        return new AuditStatement(parent::prepare($sql), $this);
    }

    public function query(string $sql): Result
    {
        return $this->attributed(fn () => parent::query($sql));
    }

    public function exec(string $sql): int|string
    {
        // Savepoint recovery must work while PostgreSQL is in an aborted transaction.
        if (preg_match('/^\s*(SAVEPOINT|RELEASE\s+SAVEPOINT|ROLLBACK\s+TO)\b/i', $sql)) {
            return parent::exec($sql);
        }

        return $this->attributed(fn () => parent::exec($sql));
    }
}
