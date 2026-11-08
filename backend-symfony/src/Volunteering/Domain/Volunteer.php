<?php

declare(strict_types=1);

namespace App\Volunteering\Domain;

use DateTimeImmutable;

/**
 * A person's volunteer profile, optionally linked to one login account. Every change moves the version; replacing the
 * availability also moves the availability revision, so readers pick the new rule set atomically.
 */
final readonly class Volunteer
{
    public const array STATUSES = ['ACTIVE', 'PAUSED', 'ARCHIVED'];

    private function __construct(
        public string $id,
        public string $personId,
        public ?string $accountId,
        public string $status,
        public int $availabilityRevision,
        public int $version,
        public DateTimeImmutable $createdAt,
        public DateTimeImmutable $updatedAt,
    ) {
        if (!\in_array($status, self::STATUSES, true)) {
            throw VolunteerException::invalid('Choose active, paused or archived.');
        }
    }

    public static function register(string $id, string $personId, ?string $accountId, DateTimeImmutable $now): self
    {
        return new self($id, $personId, $accountId, 'ACTIVE', 0, 0, $now, $now);
    }

    public static function reconstitute(string $id, string $personId, ?string $accountId, string $status, int $availabilityRevision, int $version, DateTimeImmutable $createdAt, DateTimeImmutable $updatedAt): self
    {
        return new self($id, $personId, $accountId, $status, $availabilityRevision, $version, $createdAt, $updatedAt);
    }

    public function change(?string $status, DateTimeImmutable $at): self
    {
        return new self($this->id, $this->personId, $this->accountId, (string) $status, $this->availabilityRevision, $this->version + 1, $this->createdAt, $at);
    }

    /** Links (or, with null, unlinks) the login account. Granting serving permission stays with IdentityAccess. */
    public function link(?string $accountId, DateTimeImmutable $at): self
    {
        return new self($this->id, $this->personId, $accountId, $this->status, $this->availabilityRevision, $this->version + 1, $this->createdAt, $at);
    }

    public function availability(DateTimeImmutable $at): self
    {
        return new self($this->id, $this->personId, $this->accountId, $this->status, $this->availabilityRevision + 1, $this->version + 1, $this->createdAt, $at);
    }

    /** A credential change makes an open profile screen stale. */
    public function touch(DateTimeImmutable $at): self
    {
        return new self($this->id, $this->personId, $this->accountId, $this->status, $this->availabilityRevision, $this->version + 1, $this->createdAt, $at);
    }

    public function isActive(): bool
    {
        return 'ACTIVE' === $this->status;
    }
}
