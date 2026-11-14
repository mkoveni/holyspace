<?php

declare(strict_types=1);

namespace App\Events\Presentation\Request;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class ConfigurePublicSaleRequest
{
    public function __construct(
        #[Assert\PositiveOrZero] public int $expectedVersion,
        public bool $enabled = false,
        public ?string $opensAt = null,
        public ?string $closesAt = null,
        #[Assert\Range(min: 1, max: 50)] public int $maxPerOrder = 10,
    ) {
    }
}
