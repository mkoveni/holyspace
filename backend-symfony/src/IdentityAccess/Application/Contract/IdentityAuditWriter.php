<?php

declare(strict_types=1);

namespace App\IdentityAccess\Application\Contract;

use DateTimeImmutable;

interface IdentityAuditWriter
{
    public function audit(?string $actorId, string $action, string $targetId, DateTimeImmutable $now): void;
}
