<?php

declare(strict_types=1);

namespace App\Volunteering\Application\Contract;

use DateTimeImmutable;

/**
 * A team membership (scope = team) or role approval (scope = role) as the team views return it: Java's
 * `TeamMembership` and `RoleApproval` records.
 */
final readonly class ServingPeriod
{
    public function __construct(
        public string $id,
        public string $scopeId,
        public string $volunteerId,
        public DateTimeImmutable $validFrom,
        public DateTimeImmutable $validUntil,
        public ?DateTimeImmutable $revokedAt,
    ) {
    }
}
