<?php

declare(strict_types=1);

namespace App\Giving\Presentation\Request;

use Symfony\Component\Validator\Constraints as Assert;

/** Shape checks only, as Java's CreatePledgeBody. */
final readonly class CreatePledgeRequest
{
    public function __construct(
        #[Assert\NotNull]
        #[Assert\Uuid]
        public ?string $requestId = null,
        #[Assert\NotNull]
        #[Assert\Uuid]
        public ?string $donorId = null,
        #[Assert\NotNull]
        #[Assert\Uuid]
        public ?string $fundId = null,
        // mixed, not ?int: the serializer would silently truncate 100.9 to 100 for an int property.
        #[Assert\NotNull]
        #[Assert\Type('int')]
        #[Assert\Range(min: 1, max: 1_000_000_000_000)]
        public mixed $amountMinor = null,
        #[Assert\NotBlank]
        public ?string $currency = null,
        #[Assert\NotNull]
        public ?string $startsOn = null,
        #[Assert\NotNull]
        public ?string $endsOn = null,
    ) {
    }
}
