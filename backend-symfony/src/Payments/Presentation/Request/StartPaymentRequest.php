<?php

declare(strict_types=1);

namespace App\Payments\Presentation\Request;

use Symfony\Component\Validator\Constraints as Assert;

/** Java `PaymentsApi.StartBody`: whole minor units of ZAR 5.00 to 1,000,000.00. */
final readonly class StartPaymentRequest
{
    public function __construct(
        #[Assert\NotNull] #[Assert\Uuid] public ?string $requestId = null,
        #[Assert\NotNull] #[Assert\Uuid] public ?string $donorId = null,
        #[Assert\NotNull] #[Assert\Uuid] public ?string $fundId = null,
        // mixed, not ?int: the serializer would silently truncate 500.5 to 500 for an int property.
        #[Assert\NotNull] #[Assert\Type('int')] #[Assert\Range(min: 500, max: 100_000_000)] public mixed $amountMinor = null,
        #[Assert\NotBlank] public ?string $provider = null,
        public ?bool $recurring = null,
    ) {
    }
}
