<?php

declare(strict_types=1);

namespace App\Events\Presentation\Request;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class SaveTicketTypeRequest
{
    public function __construct(
        #[Assert\PositiveOrZero] public int $expectedVersion,
        #[Assert\NotBlank] #[Assert\Length(max: 80)] public string $name,
        #[Assert\PositiveOrZero] public int $priceMinor,
        #[Assert\Length(max: 300)] public string $description = '',
        public ?int $quantityCap = null,
        public int $sortOrder = 0,
        public bool $active = true,
    ) {
    }
}
