<?php

declare(strict_types=1);

namespace App\Tests\Giving\Application;

use App\Giving\Domain\Repository\GivingHistoryRepository;
use DateTimeImmutable;

/** In-memory test double: records "TYPE:ACTION:target:reason" entries. */
final class RecordingHistory implements GivingHistoryRepository
{
    /** @var list<string> */
    public array $entries = [];
    /** @var list<string> */
    public array $actors = [];

    public function append(string $targetId, string $targetType, string $action, string $reason, string $actorId, DateTimeImmutable $at): void
    {
        $this->entries[] = "$targetType:$action:$targetId:$reason";
        $this->actors[] = $actorId;
    }
}
