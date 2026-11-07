<?php

declare(strict_types=1);

namespace App\IdentityAccess\Infrastructure\Persistence;

use App\IdentityAccess\Application\Contract\IdentityAuditWriter;
use App\Shared\Infrastructure\Persistence\DbalAuditTable;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;

final readonly class DbalIdentityAuditWriter implements IdentityAuditWriter
{
    private DbalAuditTable $table;

    public function __construct(Connection $connection)
    {
        $this->table = new DbalAuditTable($connection, 'identity_access_audit');
    }

    public function audit(?string $actorId, string $action, string $targetId, DateTimeImmutable $now): void
    {
        $this->table->append($actorId, $action, $targetId, $now);
    }
}
