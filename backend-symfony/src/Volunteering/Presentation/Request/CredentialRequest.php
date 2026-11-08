<?php

declare(strict_types=1);

namespace App\Volunteering\Presentation\Request;

use Symfony\Component\Validator\Constraints as Assert;

/** Java `VolunteeringApi.CredentialDraft`. */
final readonly class CredentialRequest
{
    public function __construct(
        #[Assert\PositiveOrZero]
        public int $expectedVersion,
        #[Assert\NotNull, Assert\Uuid]
        public ?string $typeId = null,
        #[Assert\NotNull]
        public ?string $validFrom = null,
        #[Assert\NotNull]
        public ?string $validUntil = null,
        #[Assert\NotBlank, Assert\Length(max: 500)]
        public ?string $evidence = null,
    ) {
    }
}
