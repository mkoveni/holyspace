<?php

declare(strict_types=1);

namespace App\Giving\Presentation\Request;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class ReverseGiftRequest
{
    public function __construct(
        #[Assert\NotNull]
        #[Assert\Uuid]
        public ?string $requestId = null,
        #[Assert\NotNull]
        public ?string $reversedOn = null,
        #[Assert\NotBlank]
        #[Assert\Length(max: 1000)]
        public ?string $reason = null,
    ) {
    }
}
