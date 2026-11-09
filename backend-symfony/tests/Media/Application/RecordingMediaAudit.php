<?php

declare(strict_types=1);

namespace App\Tests\Media\Application;

use App\Media\Application\Port\MediaAuditWriter;
use App\Media\Domain\SlotKey;
use DateTimeImmutable;

final class RecordingMediaAudit implements MediaAuditWriter
{
    /** @var list<array{action: string, detail: string, actor: string, kind: string, owner: string}> */
    public array $rows = [];

    public function record(SlotKey $key, string $action, string $detail, string $actorId, DateTimeImmutable $at): void
    {
        $this->rows[] = ['action' => $action, 'detail' => $detail, 'actor' => $actorId, 'kind' => $key->kind->value, 'owner' => $key->ownerId];
    }
}
