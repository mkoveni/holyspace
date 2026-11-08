<?php

declare(strict_types=1);

namespace App\Volunteering\Presentation\Request;

use Symfony\Component\Validator\Constraints as Assert;

/** Java `VolunteeringApi.Respond` and `MemberVolunteeringApi.Response`. */
final readonly class RespondRequest
{
    public function __construct(
        #[Assert\PositiveOrZero]
        public int $expectedVersion,
        #[Assert\NotBlank]
        public ?string $decision = null,
    ) {
    }
}
