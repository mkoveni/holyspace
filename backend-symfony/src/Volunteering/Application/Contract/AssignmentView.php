<?php

declare(strict_types=1);

namespace App\Volunteering\Application\Contract;

use DateTimeImmutable;

final readonly class AssignmentView
{
    /** The trailing fields are filled by the stores; readiness and staffing only need the first four. */
    public function __construct(
        public string $id,
        public string $shiftId,
        public string $volunteerId,
        public string $status,
        public string $requestId = '',
        public string $requestHash = '',
        public int $version = 0,
        public string $createdBy = '',
        public ?DateTimeImmutable $createdAt = null,
    ) {
    }
}
