<?php

declare(strict_types=1);

namespace App\Payments\Presentation\Request;

use Symfony\Component\Validator\Constraints as Assert;

/** Java `PaymentReceiptsApi.Retry` and `YocoWebhooksApi.Close`. */
final readonly class ReasonRequest
{
    public function __construct(
        #[Assert\PositiveOrZero] public int $expectedVersion,
        #[Assert\NotBlank] #[Assert\Length(max: 500)] public ?string $reason = null,
    ) {
    }
}
