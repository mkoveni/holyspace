<?php

declare(strict_types=1);

namespace App\Volunteering\Presentation\Request;

use Symfony\Component\Validator\Constraints as Assert;

/** Java `VolunteeringApi.AssignmentEdit`. */
final readonly class AssignmentEditRequest
{
    public function __construct(
        #[Assert\PositiveOrZero]
        public int $expectedVersion,
        #[Assert\NotBlank]
        public ?string $status = null,
        #[Assert\NotBlank, Assert\Length(max: 500)]
        public ?string $reason = null,
    ) {
    }
}
