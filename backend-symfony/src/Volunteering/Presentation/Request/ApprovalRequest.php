<?php

declare(strict_types=1);

namespace App\Volunteering\Presentation\Request;

use Symfony\Component\Validator\Constraints as Assert;

/** Java `VolunteeringApi.Approval`. */
final readonly class ApprovalRequest
{
    public function __construct(
        #[Assert\PositiveOrZero]
        public int $expectedVersion,
        #[Assert\NotNull, Assert\Uuid]
        public ?string $volunteerId = null,
        #[Assert\NotNull, Assert\Uuid]
        public ?string $roleId = null,
        #[Assert\NotNull]
        public ?string $validFrom = null,
        #[Assert\NotNull]
        public ?string $validUntil = null,
    ) {
    }
}
