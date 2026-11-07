<?php

declare(strict_types=1);

namespace App\Events\Application\Contract;

use DateTimeImmutable;

/** A recorded client request: the same id with the same hash replays the original target. */
final readonly class JournalRequest
{
    public function __construct(
        public string $id,
        public string $kind,
        public string $hash,
        public string $targetId,
        public string $actorId,
        public DateTimeImmutable $createdAt,
    ) {
    }
}
