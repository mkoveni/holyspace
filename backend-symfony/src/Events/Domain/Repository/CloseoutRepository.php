<?php

declare(strict_types=1);

namespace App\Events\Domain\Repository;

use App\Events\Domain\OccurrenceCloseout;

interface CloseoutRepository
{
    public function find(string $occurrenceId): ?OccurrenceCloseout;

    /** Stores the closeout and appends a history row carrying the reviewed totals. */
    public function save(OccurrenceCloseout $closeout, int $attended, int $absent, int $waitlisted, int $unresolvedNotices): void;
}
