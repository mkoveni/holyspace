<?php

declare(strict_types=1);

namespace App\Giving\Presentation\Request;

use Symfony\Component\Validator\Constraints as Assert;

/** Shape checks only, as Java's AssignBody. */
final readonly class AssignPledgeGiftRequest
{
    public function __construct(
        #[Assert\PositiveOrZero]
        public int $expectedVersion,
        #[Assert\NotNull]
        #[Assert\Uuid]
        public ?string $requestId = null,
        #[Assert\NotNull]
        #[Assert\Uuid]
        public ?string $giftId = null,
        // mixed, not ?int: the serializer would silently truncate 100.9 to 100 for an int property.
        #[Assert\NotNull]
        #[Assert\Type('int')]
        #[Assert\Range(min: 1, max: 1_000_000_000_000)]
        public mixed $amountMinor = null,
    ) {
    }
}
