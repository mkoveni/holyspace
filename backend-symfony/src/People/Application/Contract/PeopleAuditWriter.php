<?php

declare(strict_types=1);

namespace App\People\Application\Contract;

use DateTimeImmutable;

interface PeopleAuditWriter
{
    public function audit(string $actorId, string $action, string $targetReference, DateTimeImmutable $at): void;
}
