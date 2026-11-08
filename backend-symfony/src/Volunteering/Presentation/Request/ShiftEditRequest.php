<?php

declare(strict_types=1);

namespace App\Volunteering\Presentation\Request;

use Symfony\Component\Validator\Constraints as Assert;

/** Java `VolunteeringApi.ShiftEdit`; a missing `positions` is 0, as Java's primitive int was. */
final readonly class ShiftEditRequest
{
    public function __construct(
        #[Assert\PositiveOrZero]
        public int $expectedVersion,
        #[Assert\NotNull]
        public ?string $startsAt = null,
        #[Assert\NotNull]
        public ?string $endsAt = null,
        public int $positions = 0,
        #[Assert\NotBlank]
        public ?string $status = null,
    ) {
    }
}
