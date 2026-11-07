<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Shared\Application\Contract;

use DateTimeImmutable;

interface ChildrenAudit
{
    /** @return string the id of the audit row */
    public function record(string $actorId, string $action, string $targetId, string $reason, DateTimeImmutable $at): string;
}
