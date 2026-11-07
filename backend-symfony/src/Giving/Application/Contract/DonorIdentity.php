<?php

declare(strict_types=1);

namespace App\Giving\Application\Contract;

/**
 * Verified financial identity of the authenticated account, independent of email matching, households and People
 * relationships. Published for Giving and Payments.
 */
interface DonorIdentity
{
    /** The donor of the caller's active link, when that person is available; null otherwise. */
    public function current(): ?VerifiedDonor;

    /**
     * The caller's own donor id. The claimed actor must be the trusted caller, and a given $donorId must be that donor;
     * otherwise 403 "Verified donor access is required.".
     */
    public function requireOwn(string $actorId, ?string $donorId): string;
}
