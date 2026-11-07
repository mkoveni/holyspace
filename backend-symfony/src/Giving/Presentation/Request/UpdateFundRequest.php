<?php

declare(strict_types=1);

namespace App\Giving\Presentation\Request;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class UpdateFundRequest
{
    public function __construct(
        #[Assert\PositiveOrZero]
        public int $expectedVersion,
        #[Assert\NotBlank]
        #[Assert\Length(max: 100)]
        public ?string $name = null,
        #[Assert\Length(max: 1000)]
        public ?string $description = null,
        public bool $active = false,
        #[Assert\NotBlank]
        #[Assert\Length(max: 1000)]
        public ?string $reason = null,
    ) {
    }
}
