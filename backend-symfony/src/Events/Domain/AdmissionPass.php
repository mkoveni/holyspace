<?php

declare(strict_types=1);

namespace App\Events\Domain;

use DateTimeImmutable;

/**
 * A single-use attendance credential issued to a member. Only the hash of the token is kept. It is bound to the
 * member's identity version, live session, booking version and schedule revision, so any change voids it.
 */
final readonly class AdmissionPass
{
    public function __construct(
        public string $tokenHash,
        public string $registrationId,
        public string $accountId,
        public string $sessionId,
        public int $memberVersion,
        public int $registrationVersion,
        public int $scheduleRevision,
        public DateTimeImmutable $expiresAt,
        public ?DateTimeImmutable $usedAt = null,
    ) {
    }

    /** The token hash identifies the credential and must not appear in logs. */
    public function __debugInfo(): array
    {
        return ['registrationId' => $this->registrationId];
    }
}
