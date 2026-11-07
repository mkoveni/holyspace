<?php

declare(strict_types=1);

namespace App\People\Application;

use DateTimeImmutable;

/** Read model of a household. */
final readonly class HouseholdSummary
{
    public function __construct(
        public string $id,
        public string $name,
        public ?string $primaryContactMembershipId,
        public int $version,
        public DateTimeImmutable $createdAt,
        public DateTimeImmutable $updatedAt,
    ) {
    }
}
