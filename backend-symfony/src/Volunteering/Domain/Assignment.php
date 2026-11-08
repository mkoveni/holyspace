<?php

declare(strict_types=1);

namespace App\Volunteering\Domain;

use DateTimeImmutable;

/** A volunteer's commitment to a shift: proposed, confirmed or cancelled. A cancelled assignment never reopens. */
final readonly class Assignment
{
    public const array STATUSES = ['PROPOSED', 'CONFIRMED', 'CANCELLED'];

    private function __construct(
        public string $id,
        public string $requestId,
        public string $requestHash,
        public string $shiftId,
        public string $volunteerId,
        public string $status,
        public int $version,
        public string $createdBy,
        public DateTimeImmutable $createdAt,
    ) {
        if (!\in_array($status, self::STATUSES, true)) {
            throw VolunteerException::invalid('Choose a proposed or confirmed assignment.');
        }
    }

    public static function book(string $id, string $requestId, string $requestHash, string $shiftId, string $volunteerId, string $status, string $createdBy, DateTimeImmutable $createdAt): self
    {
        return new self($id, $requestId, $requestHash, $shiftId, $volunteerId, $status, 0, $createdBy, $createdAt);
    }

    public static function reconstitute(string $id, string $requestId, string $requestHash, string $shiftId, string $volunteerId, string $status, int $version, string $createdBy, DateTimeImmutable $createdAt): self
    {
        return new self($id, $requestId, $requestHash, $shiftId, $volunteerId, $status, $version, $createdBy, $createdAt);
    }

    public function change(?string $next): self
    {
        if ($this->isCancelled()) {
            throw VolunteerException::conflict('A cancelled assignment cannot reopen.');
        }

        return new self($this->id, $this->requestId, $this->requestHash, $this->shiftId, $this->volunteerId, (string) $next, $this->version + 1, $this->createdBy, $this->createdAt);
    }

    public function isCancelled(): bool
    {
        return 'CANCELLED' === $this->status;
    }
}
