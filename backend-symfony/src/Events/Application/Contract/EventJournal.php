<?php

declare(strict_types=1);

namespace App\Events\Application\Contract;

use App\Events\Domain\EventOccurrence;
use DateTimeImmutable;

/** Write side of the Events audit trail and request idempotency log. Callers write inside their own transaction. */
interface EventJournal
{
    /** Records a client request; a reused id is a conflict. */
    public function record(JournalRequest $request): void;

    public function audit(string $actorId, string $action, string $targetId, string $reason, DateTimeImmutable $at): void;

    public function registration(string $actorId, string $action, string $registrationId, string $reason, DateTimeImmutable $at): void;

    /** Appends an occurrence history row snapshotting its schedule, venue and versions. */
    public function occurrence(string $actorId, string $action, EventOccurrence $occurrence, string $reason, DateTimeImmutable $at): void;
}
