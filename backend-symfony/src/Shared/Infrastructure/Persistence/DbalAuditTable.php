<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Persistence;

use DateTimeImmutable;
use DateTimeZone;
use Doctrine\DBAL\Connection;
use Symfony\Component\Uid\Uuid;

/**
 * Append-only writer for the per-context audit tables (identity_access_audit, people_audit, ...).
 * Each context builds one for its own table and exposes it through its own audit contract.
 */
final readonly class DbalAuditTable
{
    /**
     * @param string $table        trusted table name from infrastructure wiring, never request input
     * @param string $targetColumn target_id for most contexts, target_reference for People
     */
    public function __construct(
        private Connection $connection,
        private string $table,
        private string $targetColumn = 'target_id',
    ) {
    }

    /**
     * @param array<string, string> $extra additional columns such as reason
     *
     * @return string the id of the new audit row
     */
    public function append(
        ?string $actorId,
        string $action,
        string $target,
        DateTimeImmutable $occurredAt,
        array $extra = [],
    ): string {
        $id = Uuid::v4()->toRfc4122();
        $this->connection->insert($this->table, [
            'id' => $id,
            'actor_id' => $actorId,
            $this->targetColumn => $target,
            'action' => $action,
            'occurred_at' => $occurredAt->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s.uP'),
            ...$extra,
        ]);

        return $id;
    }
}
