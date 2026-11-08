<?php

declare(strict_types=1);

namespace App\Communities\Application\Contract;

use DateTimeImmutable;

final readonly class HistoryEntry
{
    public function __construct(
        public string $id,
        public string $targetId,
        public string $action,
        public string $reason,
        public string $actorId,
        public DateTimeImmutable $occurredAt,
    ) {
    }
}
