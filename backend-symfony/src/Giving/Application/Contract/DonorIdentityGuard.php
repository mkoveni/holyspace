<?php

declare(strict_types=1);

namespace App\Giving\Application\Contract;

/** Command-only financial identity guard. Acquire it inside the transaction before any People or fund lock. */
interface DonorIdentityGuard
{
    /** Locks the caller's donor link and proves it is active and belongs to $donorId; otherwise 403. */
    public function lockOwn(string $actorId, string $donorId): void;
}
