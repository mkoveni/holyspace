<?php

declare(strict_types=1);

namespace App\Events\Domain\Repository;

use DateTimeImmutable;

/** Whether an occurrence sends automatic visitor messages, and who approved it. The caller holds the occurrence lock. */
interface VisitMessagingRepository
{
    public function approve(string $occurrenceId, string $actorId, DateTimeImmutable $at): void;

    public function withdraw(string $occurrenceId): void;
}
