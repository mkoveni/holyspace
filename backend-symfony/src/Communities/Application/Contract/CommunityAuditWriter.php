<?php

declare(strict_types=1);

namespace App\Communities\Application\Contract;

use DateTimeImmutable;

interface CommunityAuditWriter
{
    public function record(
        string $communityId,
        string $targetId,
        string $action,
        string $reason,
        string $actorId,
        DateTimeImmutable $at,
    ): void;
}
