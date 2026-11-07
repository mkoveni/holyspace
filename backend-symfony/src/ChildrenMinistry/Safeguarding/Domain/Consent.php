<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Safeguarding\Domain;

use App\ChildrenMinistry\Shared\Domain\ChildrenException;
use App\ChildrenMinistry\Shared\Domain\ChildrenRules;
use DateTimeImmutable;

/** A guardian's evidenced acceptance of a programme policy for a child over a period. Revoking never erases it. */
final readonly class Consent
{
    private function __construct(
        public string $id,
        public string $programmeId,
        public string $policyId,
        public string $childId,
        public string $authorityId,
        public DateTimeImmutable $validFrom,
        public DateTimeImmutable $validUntil,
        public DateTimeImmutable $acceptedAt,
        public string $recordedBy,
        public string $evidenceReference,
        public ?DateTimeImmutable $revokedAt,
        public ?string $revocationReason,
        public int $version,
    ) {
        ChildrenRules::window($validFrom, $validUntil);
    }

    /** Acceptance must be evidenced, not in the future, not before the policy existed, and within the authority's period. */
    public static function record(
        string $id,
        string $programmeId,
        ConsentPolicy $policy,
        string $childId,
        GuardianAuthority $authority,
        DateTimeImmutable $validFrom,
        DateTimeImmutable $validUntil,
        ?DateTimeImmutable $acceptedAt,
        ?string $evidenceReference,
        string $recordedBy,
        DateTimeImmutable $now,
    ): self {
        if ($authority->childId !== $childId
            || !$authority->covers($validFrom, $validUntil)
            || $policy->programmeId !== $programmeId
            || null === $acceptedAt
            || $acceptedAt > $now
            || $acceptedAt < $policy->createdAt
            || !$authority->covers($acceptedAt, $acceptedAt)) {
            throw ChildrenException::invalid('Consent requires verified authority, the correct policy, and evidenced acceptance within its authority period.');
        }

        return new self($id, $programmeId, $policy->id, $childId, $authority->id, $validFrom, $validUntil, $acceptedAt, $recordedBy, ChildrenRules::text($evidenceReference, 255), null, null, 0);
    }

    public static function reconstitute(
        string $id,
        string $programmeId,
        string $policyId,
        string $childId,
        string $authorityId,
        DateTimeImmutable $validFrom,
        DateTimeImmutable $validUntil,
        DateTimeImmutable $acceptedAt,
        string $recordedBy,
        string $evidenceReference,
        ?DateTimeImmutable $revokedAt,
        ?string $revocationReason,
        int $version,
    ): self {
        return new self($id, $programmeId, $policyId, $childId, $authorityId, $validFrom, $validUntil, $acceptedAt, $recordedBy, $evidenceReference, $revokedAt, $revocationReason, $version);
    }

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

        return new self($this->id, $this->programmeId, $this->policyId, $this->childId, $this->authorityId, $this->validFrom, $this->validUntil, $this->acceptedAt, $this->recordedBy, $this->evidenceReference, $now, ChildrenRules::text($reason, 500), $this->version + 1);
    }
}
