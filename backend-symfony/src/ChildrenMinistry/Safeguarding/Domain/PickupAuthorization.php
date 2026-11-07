<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Safeguarding\Domain;

use App\ChildrenMinistry\Shared\Domain\ChildrenException;
use App\ChildrenMinistry\Shared\Domain\ChildrenRules;
use DateTimeImmutable;

/** Permission for one collector to take a child home from a programme, backed by a guardian's authority. */
final readonly class PickupAuthorization
{
    private function __construct(
        public string $id,
        public string $programmeId,
        public string $childId,
        public string $collectorId,
        public string $authorityId,
        public DateTimeImmutable $validFrom,
        public DateTimeImmutable $validUntil,
        public string $evidenceReference,
        public string $recordedBy,
        public ?DateTimeImmutable $revokedAt,
        public ?string $revocationReason,
        public int $version,
    ) {
        ChildrenRules::window($validFrom, $validUntil);
        if ($childId === $collectorId) {
            throw ChildrenException::invalid('A child cannot collect themselves.');
        }
    }

    public static function grant(
        string $id,
        string $programmeId,
        string $childId,
        string $collectorId,
        GuardianAuthority $authority,
        DateTimeImmutable $validFrom,
        DateTimeImmutable $validUntil,
        ?string $evidenceReference,
        string $recordedBy,
    ): self {
        if ($authority->childId !== $childId || !$authority->covers($validFrom, $validUntil)) {
            throw ChildrenException::invalid('Pickup permission requires verified guardian authority covering its period.');
        }

        return new self($id, $programmeId, $childId, $collectorId, $authority->id, $validFrom, $validUntil, ChildrenRules::text($evidenceReference, 255), $recordedBy, null, null, 0);
    }

    public static function reconstitute(
        string $id,
        string $programmeId,
        string $childId,
        string $collectorId,
        string $authorityId,
        DateTimeImmutable $validFrom,
        DateTimeImmutable $validUntil,
        string $evidenceReference,
        string $recordedBy,
        ?DateTimeImmutable $revokedAt,
        ?string $revocationReason,
        int $version,
    ): self {
        return new self($id, $programmeId, $childId, $collectorId, $authorityId, $validFrom, $validUntil, $evidenceReference, $recordedBy, $revokedAt, $revocationReason, $version);
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

        return new self($this->id, $this->programmeId, $this->childId, $this->collectorId, $this->authorityId, $this->validFrom, $this->validUntil, $this->evidenceReference, $this->recordedBy, $now, ChildrenRules::text($reason, 500), $this->version + 1);
    }
}
