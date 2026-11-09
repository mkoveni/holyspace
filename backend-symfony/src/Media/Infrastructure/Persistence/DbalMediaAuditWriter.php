<?php

declare(strict_types=1);

namespace App\Media\Infrastructure\Persistence;

use App\Media\Application\Port\MediaAuditWriter;
use App\Media\Domain\SlotKey;
use App\Shared\Infrastructure\Persistence\DbalAuditTable;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;

final readonly class DbalMediaAuditWriter implements MediaAuditWriter
{
    private DbalAuditTable $table;

    public function __construct(Connection $connection)
    {
        $this->table = new DbalAuditTable($connection, 'media_audit');
    }

    public function record(SlotKey $key, string $action, string $detail, string $actorId, DateTimeImmutable $at): void
    {
        $this->table->append($actorId, $action, $key->ownerId, $at, ['owner_type' => $key->ownerType->value, 'kind' => $key->kind->value, 'reason' => $detail]);
    }
}
