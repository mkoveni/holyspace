<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Messaging\Domain;

use DateTimeImmutable;

/** A coordinator's record that an address was confirmed with the guardian. It lapses, can be revoked, and covers one contact. */
final readonly class VerifiedGuardianEmail
{
    public function __construct(
        public string $id,
        public string $guardianId,
        public string $contactId,
        public string $email,
        public string $verifiedBy,
        public DateTimeImmutable $verifiedAt,
        public DateTimeImmutable $validUntil,
        public string $evidence,
        public ?DateTimeImmutable $revokedAt,
    ) {
    }

    public function valid(DateTimeImmutable $at): bool
    {
        return null === $this->revokedAt && $this->verifiedAt <= $at && $this->validUntil > $at;
    }

    public function __debugInfo(): array
    {
        return ['id' => $this->id];
    }
}
