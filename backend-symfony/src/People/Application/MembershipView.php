<?php

declare(strict_types=1);

namespace App\People\Application;

use DateTimeImmutable;

/** Read model of a dated household membership. */
final readonly class MembershipView
{
    public function __construct(
        public string $id,
        public string $householdId,
        public string $personId,
        public bool $primary,
        public DateTimeImmutable $validFrom,
        public ?DateTimeImmutable $validTo,
    ) {
    }
}
