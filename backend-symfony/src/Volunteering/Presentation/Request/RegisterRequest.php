<?php

declare(strict_types=1);

namespace App\Volunteering\Presentation\Request;

use Symfony\Component\Validator\Constraints as Assert;

/** Java `VolunteeringApi.Register`. */
final readonly class RegisterRequest
{
    public function __construct(
        #[Assert\NotNull, Assert\Uuid]
        public ?string $personId = null,
        #[Assert\Uuid]
        public ?string $accountId = null,
    ) {
    }
}
