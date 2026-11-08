<?php

declare(strict_types=1);

namespace App\Volunteering\Application\Contract;

use DateTimeImmutable;

final readonly class VolunteerView
{
    /** The trailing fields are filled by the stores; readiness only needs the first four. */
    public function __construct(
        public string $id,
        public string $personId,
        public string $status,
        public int $availabilityRevision,
        public ?string $accountId = null,
        public int $version = 0,
        public ?DateTimeImmutable $createdAt = null,
        public ?DateTimeImmutable $updatedAt = null,
    ) {
    }
}
