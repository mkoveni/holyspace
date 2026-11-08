<?php

declare(strict_types=1);

namespace App\Volunteering\Presentation\Request;

use Symfony\Component\Validator\Constraints as Assert;

/** Java `VolunteeringApi.Assign`. */
final readonly class AssignRequest
{
    public function __construct(
        #[Assert\PositiveOrZero]
        public int $expectedVersion,
        #[Assert\NotNull, Assert\Uuid]
        public ?string $requestId = null,
        #[Assert\NotNull, Assert\Uuid]
        public ?string $shiftId = null,
        #[Assert\NotNull, Assert\Uuid]
        public ?string $volunteerId = null,
        #[Assert\NotBlank]
        public ?string $status = null,
    ) {
    }
}
