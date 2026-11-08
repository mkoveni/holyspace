<?php

declare(strict_types=1);

namespace App\Volunteering\Application\Contract;

use DateTimeImmutable;

/** Java `ServingAudit`: one `serving_audit` row per change, written in the command transaction. */
interface ServingAudit
{
    public function record(string $actorId, string $action, string $targetId, DateTimeImmutable $at, string $reason = ''): void;
}
