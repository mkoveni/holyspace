<?php

declare(strict_types=1);

namespace App\Giving\Presentation\Request;

use Symfony\Component\Validator\Constraints as Assert;

/** Java's LinkBody. `active` and `expectedVersion` are required here (see the G7 decision log). */
final readonly class VerifyDonorLinkRequest
{
    public function __construct(
        public bool $active,
        #[Assert\PositiveOrZero]
        public int $expectedVersion,
        #[Assert\NotNull]
        #[Assert\Uuid]
        public ?string $personId = null,
        #[Assert\NotBlank]
        #[Assert\Length(max: 1000)]
        public ?string $evidence = null,
    ) {
    }
}
