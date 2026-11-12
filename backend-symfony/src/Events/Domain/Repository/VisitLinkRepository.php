<?php

declare(strict_types=1);

namespace App\Events\Domain\Repository;

use App\Events\Domain\VisitLink;
use DateTimeImmutable;

interface VisitLinkRepository
{
    /** The occurrence's link; the caller holds the occurrence lock, which serialises changes to it. */
    public function findUnlocked(string $occurrenceId): ?VisitLink;

    public function save(VisitLink $link, string $actorId, DateTimeImmutable $at): void;
}
