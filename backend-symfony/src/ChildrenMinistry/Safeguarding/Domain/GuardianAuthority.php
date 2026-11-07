<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Safeguarding\Domain;

use App\ChildrenMinistry\Shared\Domain\ChildrenException;
use App\ChildrenMinistry\Shared\Domain\ChildrenRules;
use DateTimeImmutable;

/** A verified adult's authority over a child for a period. Revoking it never erases it. */
final readonly class GuardianAuthority
{
    private function __construct(
        public string $id,
        public string $childId,
        public string $guardianId,
        public DateTimeImmutable $validFrom,
        public DateTimeImmutable $validUntil,
        public string $evidenceReference,
        public string $verifiedBy,
        public ?DateTimeImmutable $revokedAt,
        public ?string $revocationReason,
        public int $version,
    ) {
        ChildrenRules::window($validFrom, $validUntil);
        if ($childId === $guardianId) {
            throw ChildrenException::invalid('Child and guardian must be different people.');
        }
    }

    public static function verify(
        string $id,
        string $childId,
        string $guardianId,
        DateTimeImmutable $validFrom,
        DateTimeImmutable $validUntil,
        ?string $evidenceReference,
        string $verifiedBy,
    ): self {
        return new self($id, $childId, $guardianId, $validFrom, $validUntil, ChildrenRules::text($evidenceReference, 255), $verifiedBy, null, null, 0);
    }

    public static function reconstitute(
        string $id,
        string $childId,
        string $guardianId,
        DateTimeImmutable $validFrom,
        DateTimeImmutable $validUntil,
        string $evidenceReference,
        string $verifiedBy,
        ?DateTimeImmutable $revokedAt,
        ?string $revocationReason,
        int $version,
    ): self {
        return new self($id, $childId, $guardianId, $validFrom, $validUntil, $evidenceReference, $verifiedBy, $revokedAt, $revocationReason, $version);
    }

    /** Whether the authority is unrevoked and spans the whole interval [from, until]. */
    public function covers(DateTimeImmutable $from, DateTimeImmutable $until): bool
    {
        return null === $this->revokedAt && ChildrenRules::covers($this->validFrom, $this->validUntil, $from, $until);
    }

    public function revoke(int $expectedVersion, ?string $reason, DateTimeImmutable $now): self
    {
        ChildrenRules::version($this->version, $expectedVersion);
        if (null !== $this->revokedAt) {
            throw ChildrenException::conflict('This record is already revoked.');
        }

        return new self($this->id, $this->childId, $this->guardianId, $this->validFrom, $this->validUntil, $this->evidenceReference, $this->verifiedBy, $now, ChildrenRules::text($reason, 500), $this->version + 1);
    }
}
