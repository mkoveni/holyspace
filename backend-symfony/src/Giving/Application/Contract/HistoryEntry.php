<?php

declare(strict_types=1);

namespace App\Giving\Application\Contract;

use DateTimeImmutable;

/** One giving_history row of a gift or pledge (Java GivingReader.History). */
final readonly class HistoryEntry
{
    public function __construct(
        public string $action,
        public string $reason,
        public string $actorId,
        public DateTimeImmutable $occurredAt,
    ) {
    }
}
