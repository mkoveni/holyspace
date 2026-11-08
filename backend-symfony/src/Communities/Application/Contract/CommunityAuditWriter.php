<?php

declare(strict_types=1);

namespace App\Communities\Application\Contract;

use DateTimeImmutable;

/** Appends to `community_history` inside the command's transaction. */
interface CommunityAuditWriter
{
    public function record(string $communityId, string $targetId, string $action, string $reason, string $actorId, DateTimeImmutable $at): void;
}
