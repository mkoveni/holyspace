<?php

declare(strict_types=1);

namespace App\Volunteering\Presentation\Request;

use Symfony\Component\Validator\Constraints as Assert;

/** Java `VolunteeringApi.CredentialTypeDraft`. */
final readonly class CredentialTypeRequest
{
    public function __construct(
        #[Assert\NotBlank, Assert\Length(max: 80)]
        public ?string $code = null,
        #[Assert\NotBlank, Assert\Length(max: 150)]
        public ?string $name = null,
    ) {
    }
}
