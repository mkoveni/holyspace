<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Sessions\Domain;

use App\ChildrenMinistry\Shared\Domain\ChildrenRules;
use DateTimeImmutable;

/** A person cleared to serve with children: screened, trained and not revoked. */
final readonly class Worker
{
    public string $evidenceReference;

    private function __construct(
        public string $personId,
        public string $accountId,
        public DateTimeImmutable $verifiedFrom,
        public DateTimeImmutable $screenedUntil,
        public DateTimeImmutable $trainedUntil,
        string $evidenceReference,
        public string $verifiedBy,
        public ?DateTimeImmutable $revokedAt,
        public int $version,
    ) {
        ChildrenRules::window($verifiedFrom, $screenedUntil);
        ChildrenRules::window($verifiedFrom, $trainedUntil);
        $this->evidenceReference = ChildrenRules::text($evidenceReference, 255);
    }

    public static function register(string $personId, string $accountId, DateTimeImmutable $now, DateTimeImmutable $screenedUntil, DateTimeImmutable $trainedUntil, ?string $evidenceReference, string $verifiedBy): self
    {
        return new self($personId, $accountId, $now, $screenedUntil, $trainedUntil, (string) $evidenceReference, $verifiedBy, null, 0);
    }

    public static function reconstitute(
        string $personId,
        string $accountId,
        DateTimeImmutable $verifiedFrom,
        DateTimeImmutable $screenedUntil,
        DateTimeImmutable $trainedUntil,
        string $evidenceReference,
        string $verifiedBy,
        ?DateTimeImmutable $revokedAt,
        int $version,
    ): self {
        return new self($personId, $accountId, $verifiedFrom, $screenedUntil, $trainedUntil, $evidenceReference, $verifiedBy, $revokedAt, $version);
    }

    /** A review renews clearance from now, or revokes it keeping the original start. The caller checks the version first. */
    public function review(DateTimeImmutable $now, bool $revoked, DateTimeImmutable $screenedUntil, DateTimeImmutable $trainedUntil, ?string $evidenceReference, string $reviewedBy): self
    {
        return new self($this->personId, $this->accountId, $revoked ? $this->verifiedFrom : $now, $screenedUntil, $trainedUntil, (string) $evidenceReference, $reviewedBy, $revoked ? $now : null, $this->version + 1);
    }

    /** Whether clearance is in force now and screening and training both last through the session. */
    public function eligible(DateTimeImmutable $now, DateTimeImmutable $through): bool
    {
        return null === $this->revokedAt && $this->verifiedFrom <= $now && $this->screenedUntil >= $through && $this->trainedUntil >= $through;
    }
}
