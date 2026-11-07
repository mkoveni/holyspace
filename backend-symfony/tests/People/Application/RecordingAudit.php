<?php

declare(strict_types=1);

namespace App\Tests\People\Application;

use App\People\Application\Contract\PeopleAuditWriter;
use DateTimeImmutable;

/** In-memory test double: no database, no identity context. */
final class RecordingAudit implements PeopleAuditWriter
{
    /** @var list<string> */
    public array $entries = [];

    public function audit(string $actorId, string $action, string $targetReference, DateTimeImmutable $at): void
    {
        $this->entries[] = "$actorId:$action:$targetReference";
    }
}
