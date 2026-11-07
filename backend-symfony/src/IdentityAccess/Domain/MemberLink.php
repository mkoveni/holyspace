<?php

declare(strict_types=1);

namespace App\IdentityAccess\Domain;

use DateTimeImmutable;

/** A verified link between a login account and a person. Once linked, an account can never be pointed at someone else. */
final readonly class MemberLink
{
    private function __construct(
        public string $accountId,
        public string $personId,
        public bool $active,
        public int $version,
        public string $verifiedBy,
        public DateTimeImmutable $verifiedAt,
    ) {
        if ($version < 1) {
            throw IdentityException::invalid('A member link requires verified account and person identity.');
        }
    }

    public static function verify(string $accountId, string $personId, string $actorId, DateTimeImmutable $now): self
    {
        return new self($accountId, $personId, true, 1, $actorId, $now);
    }

    public static function reconstitute(
        string $accountId,
        string $personId,
        bool $active,
        int $version,
        string $verifiedBy,
        DateTimeImmutable $verifiedAt,
    ): self {
        return new self($accountId, $personId, $active, $version, $verifiedBy, $verifiedAt);
    }

    public function revise(string $personId, bool $enabled, int $expectedVersion, string $actorId, DateTimeImmutable $now): self
    {
        if ($this->version !== $expectedVersion || $this->personId !== $personId) {
            throw IdentityException::conflict('Refresh member access. Verified identities cannot be reassigned to a different person.');
        }

        return new self($this->accountId, $this->personId, $enabled, $this->version + 1, $actorId, $now);
    }
}
