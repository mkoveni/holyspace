<?php

declare(strict_types=1);

namespace App\Giving\Application\Contract;

/**
 * Trusted one-time Payments handoff only (a mobile checkout completed outside the caller's live session). The caller
 * must separately verify the session it bound to the handoff. Call it inside the transaction, before People and fund locks.
 */
interface DonorCheckoutGuard
{
    /** Locks the account's donor link; true only when it is active, belongs to $donorId and that person is available. */
    public function lockEligible(string $accountId, string $donorId): bool;
}
