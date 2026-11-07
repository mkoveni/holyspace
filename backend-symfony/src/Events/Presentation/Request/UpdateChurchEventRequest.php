<?php

declare(strict_types=1);

namespace App\Events\Presentation\Request;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class UpdateChurchEventRequest
{
    public function __construct(
        #[Assert\PositiveOrZero]
        public int $expectedVersion,
        #[Assert\NotBlank]
        #[Assert\Length(max: 1000)]
        public string $reason,
        public string $title = '',
        public string $type = '',
        public string $description = '',
        public bool $active = false,
    ) {
    }
}
