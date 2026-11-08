<?php

declare(strict_types=1);

namespace App\Payments\Presentation\Request;

use Symfony\Component\Validator\Constraints as Assert;

/** Java `DonorGivingApi.StartBody`: the donor is never part of the request. */
final readonly class DonorPaymentRequest
{
    public function __construct(
        #[Assert\NotNull] #[Assert\Uuid] public ?string $requestId = null,
        #[Assert\NotNull] #[Assert\Uuid] public ?string $fundId = null,
        #[Assert\NotNull] #[Assert\Type('int')] #[Assert\Range(min: 500, max: 100_000_000)] public mixed $amountMinor = null,
        #[Assert\NotBlank] public ?string $provider = null,
        public bool $recurring = false,
    ) {
    }
}
