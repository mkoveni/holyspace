<?php

declare(strict_types=1);

namespace App\Volunteering\Domain;

use DateTimeImmutable;

/** A volunteer's approval for a team role over a period; revoking keeps the record. */
final readonly class RoleApproval
{
    private function __construct(
        public string $id,
        public string $roleId,
        public string $volunteerId,
        public DateTimeImmutable $validFrom,
        public DateTimeImmutable $validUntil,
        public ?DateTimeImmutable $revokedAt,
    ) {
        ServingRules::period($validFrom, $validUntil);
    }

    public static function grant(string $id, string $roleId, string $volunteerId, DateTimeImmutable $validFrom, DateTimeImmutable $validUntil): self
    {
        return new self($id, $roleId, $volunteerId, $validFrom, $validUntil, null);
    }

    public function window(): ValidityWindow
    {
        return new ValidityWindow($this->validFrom, $this->validUntil, $this->revokedAt);
    }

    /** True when an unrevoked window shares any time with this approval. */
    public function overlapsAny(ValidityWindow ...$windows): bool
    {
        return $this->window()->overlapsAny(...$windows);
    }
}
