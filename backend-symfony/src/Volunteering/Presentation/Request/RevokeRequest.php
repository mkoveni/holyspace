<?php

declare(strict_types=1);

namespace App\Volunteering\Presentation\Request;

use Symfony\Component\Validator\Constraints as Assert;

/** Java `VolunteeringApi.Revoke`: a missing `roleApproval` means a membership, as Java's primitive boolean did. */
final readonly class RevokeRequest
{
    public function __construct(
        #[Assert\PositiveOrZero]
        public int $expectedVersion,
        #[Assert\NotNull, Assert\Uuid]
        public ?string $volunteerId = null,
        #[Assert\NotNull, Assert\Uuid]
        public ?string $recordId = null,
        public bool $roleApproval = false,
    ) {
    }
}
