<?php

declare(strict_types=1);

namespace App\Giving\Application\Contract;

use DateTimeImmutable;

final readonly class GiftHistoryEntry
{
    public function __construct(
        public string $action,
        public string $reason,
        public string $actorId,
        public DateTimeImmutable $occurredAt,
    ) {
    }
}
