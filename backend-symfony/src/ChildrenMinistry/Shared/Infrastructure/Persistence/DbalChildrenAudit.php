<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Shared\Infrastructure\Persistence;

use App\ChildrenMinistry\Shared\Application\Contract\ChildrenAudit;
use App\Shared\Infrastructure\Persistence\DbalAuditTable;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;

final readonly class DbalChildrenAudit implements ChildrenAudit
{
    private DbalAuditTable $table;

    public function __construct(Connection $connection)
    {
        $this->table = new DbalAuditTable($connection, 'children_audit');
    }

    public function record(string $actorId, string $action, string $targetId, string $reason, DateTimeImmutable $at): string
    {
        return $this->table->append($actorId, $action, $targetId, $at, ['reason' => $reason]);
    }
}
