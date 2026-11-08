<?php

declare(strict_types=1);

namespace App\Tests\Communities\Application;

use App\Communities\Application\Contract\CommunityAuditWriter;
use DateTimeImmutable;

final class RecordingAudit implements CommunityAuditWriter
{
    /** @var list<string> */
    public array $actions = [];

    public function record(string $communityId, string $targetId, string $action, string $reason, string $actorId, DateTimeImmutable $at): void
    {
        $this->actions[] = $action;
    }
}
