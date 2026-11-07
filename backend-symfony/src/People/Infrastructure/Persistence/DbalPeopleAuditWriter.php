<?php

declare(strict_types=1);

namespace App\People\Infrastructure\Persistence;

use App\People\Application\Contract\PeopleAuditWriter;
use App\Shared\Infrastructure\Persistence\DbalAuditTable;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;

final readonly class DbalPeopleAuditWriter implements PeopleAuditWriter
{
    private DbalAuditTable $table;

    public function __construct(Connection $connection)
    {
        $this->table = new DbalAuditTable($connection, 'people_audit', 'target_reference');
    }

    public function audit(string $actorId, string $action, string $targetReference, DateTimeImmutable $at): void
    {
        $this->table->append($actorId, $action, $targetReference, $at);
    }
}
