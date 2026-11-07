<?php

declare(strict_types=1);

namespace App\Events\Presentation\Request;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class ReopenEventCloseoutRequest
{
    public function __construct(
        #[Assert\PositiveOrZero]
        public int $expectedVersion,
        #[Assert\NotBlank]
        #[Assert\Length(max: 2000)]
        public string $reason,
    ) {
    }
}
