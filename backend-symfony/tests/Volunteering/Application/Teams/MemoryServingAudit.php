<?php

declare(strict_types=1);

namespace App\Tests\Volunteering\Application\Teams;

use App\Volunteering\Application\Contract\ServingAudit;
use DateTimeImmutable;

final class MemoryServingAudit implements ServingAudit
{
    /** @var list<string> "action:target" */
    public array $rows = [];

    public function record(string $actorId, string $action, string $targetId, DateTimeImmutable $at, string $reason = ''): void
    {
        $this->rows[] = $action.':'.$targetId;
    }
}
