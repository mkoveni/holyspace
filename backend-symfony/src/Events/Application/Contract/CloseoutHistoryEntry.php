<?php

declare(strict_types=1);

namespace App\Events\Application\Contract;

use DateTimeImmutable;

final readonly class CloseoutHistoryEntry
{
    public function __construct(
        public string $id,
        public int $version,
        public string $action,
        public string $note,
        public string $followUp,
        public int $attended,
        public int $absent,
        public int $waitlisted,
        public int $unresolvedNotices,
        public string $actorId,
        public string $actorName,
        public DateTimeImmutable $occurredAt,
    ) {
    }
}
