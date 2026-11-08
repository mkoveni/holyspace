<?php

declare(strict_types=1);

namespace App\Payments\Presentation\Request;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class RefundRequest
{
    public function __construct(
        #[Assert\NotNull] #[Assert\Uuid] public ?string $chargeId = null,
        #[Assert\NotBlank] #[Assert\Length(max: 150)] public ?string $providerReference = null,
        #[Assert\NotNull] #[Assert\Type('int')] #[Assert\Range(min: 1, max: 100_000_000)] public mixed $amountMinor = null,
        #[Assert\NotNull] #[Assert\Date] public ?string $refundedOn = null,
        #[Assert\NotBlank] #[Assert\Length(max: 1000)] public ?string $reason = null,
    ) {
    }
}
