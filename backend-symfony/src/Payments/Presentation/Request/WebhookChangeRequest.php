<?php

declare(strict_types=1);

namespace App\Payments\Presentation\Request;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class WebhookChangeRequest
{
    public function __construct(
        #[Assert\PositiveOrZero] public int $expectedVersion,
        #[Assert\NotNull] #[Assert\Uuid] public ?string $requestId = null,
        #[Assert\NotBlank] public ?string $mode = null,
        #[Assert\NotBlank] #[Assert\Length(max: 500)] public ?string $reason = null,
    ) {
    }
}
