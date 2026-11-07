<?php

declare(strict_types=1);

namespace App\Giving\Domain;

use DateTimeImmutable;

/**
 * Staff-verified financial identity: this account belongs to this donor. It is independent of email matching, households
 * and People relationships, can be revoked and restored, and can never be reassigned to another person.
 */
final readonly class DonorLink
{
    private function __construct(
        public string $accountId,
        public string $personId,
        public bool $active,
        public int $version,
        public string $verifiedBy,
        public DateTimeImmutable $verifiedAt,
    ) {
        if ('' === $accountId || '' === $personId || '' === $verifiedBy || $version < 1) {
            throw GivingException::invalid('A donor link requires account, person and verification evidence.');
        }
    }

    /** A new, active link at version 1. */
    public static function verify(string $accountId, string $personId, string $actorId, DateTimeImmutable $now): self
    {
        return new self($accountId, $personId, true, 1, $actorId, $now);
    }

    public static function reconstitute(string $accountId, string $personId, bool $active, int $version, string $verifiedBy, DateTimeImmutable $verifiedAt): self
    {
        return new self($accountId, $personId, $active, $version, $verifiedBy, $verifiedAt);
    }

    /** Revokes or restores the link for the same person; the version is checked first. */
    public function revise(string $personId, bool $active, int $expectedVersion, string $actorId, DateTimeImmutable $now): self
    {
        GivingRules::version($this->version, $expectedVersion);
        if ($this->personId !== $personId) {
            throw GivingException::conflict('A verified account cannot be reassigned to another donor. Revoke this link and contact support to correct identity evidence.');
        }

        return new self($this->accountId, $this->personId, $active, $this->version + 1, $actorId, $now);
    }
}
