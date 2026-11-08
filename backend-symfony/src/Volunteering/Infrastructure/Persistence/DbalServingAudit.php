<?php

declare(strict_types=1);

namespace App\Volunteering\Infrastructure\Persistence;

use App\Shared\Infrastructure\Persistence\DbalAuditTable;
use App\Volunteering\Application\Contract\ServingAudit;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;

final readonly class DbalServingAudit implements ServingAudit
{
    private DbalAuditTable $table;

    public function __construct(Connection $connection)
    {
        $this->table = new DbalAuditTable($connection, 'serving_audit');
    }

    public function record(string $actorId, string $action, string $targetId, DateTimeImmutable $at, string $reason = ''): void
    {
        $this->table->append($actorId, $action, $targetId, $at, ['reason' => $reason]);
    }
}
