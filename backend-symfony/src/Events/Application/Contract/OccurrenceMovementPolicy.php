<?php

declare(strict_types=1);

namespace App\Events\Application\Contract;

/**
 * Implemented by contexts that attach their own evidence to an occurrence (for example a prepared group roster) and must
 * protect it before the occurrence is moved. Implementations must be read-only.
 */
interface OccurrenceMovementPolicy
{
    public function movable(string $occurrenceId): bool;
}
