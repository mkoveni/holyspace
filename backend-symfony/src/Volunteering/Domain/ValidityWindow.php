<?php

declare(strict_types=1);

namespace App\Volunteering\Domain;

use DateTimeImmutable;

/** A period during which a membership, approval or credential applies, unless revoked. */
final readonly class ValidityWindow
{
    public function __construct(
        public DateTimeImmutable $validFrom,
        public DateTimeImmutable $validUntil,
        public ?DateTimeImmutable $revokedAt = null,
    ) {
        ServingRules::period($validFrom, $validUntil);
    }

    public function covers(DateTimeImmutable $from, DateTimeImmutable $until): bool
    {
        return null === $this->revokedAt && ServingRules::covers($this->validFrom, $this->validUntil, $from, $until);
    }
}
