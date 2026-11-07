<?php

declare(strict_types=1);

namespace App\Giving\Presentation\Request;

use Symfony\Component\Validator\Constraints as Assert;

/** Shape checks only, as Java's ReconcileBody: a whole expected amount and 1-100 gift ids. */
final readonly class CreateReconciliationRequest
{
    /** @param list<mixed>|null $giftIds */
    public function __construct(
        #[Assert\NotNull]
        #[Assert\Uuid]
        public ?string $requestId = null,
        #[Assert\NotBlank]
        #[Assert\Length(max: 200)]
        public ?string $reference = null,
        // mixed, not ?int: the serializer would silently truncate 100.9 to 100 for an int property.
        #[Assert\NotNull]
        #[Assert\Type('int')]
        #[Assert\Range(min: 1, max: 100_000_000_000_000)]
        public mixed $expectedMinor = null,
        #[Assert\NotBlank]
        public ?string $currency = null,
        #[Assert\NotNull]
        public ?string $reconciledOn = null,
        #[Assert\Length(max: 1000)]
        public ?string $reason = null,
        #[Assert\NotNull]
        #[Assert\Count(min: 1, max: 100)]
        #[Assert\All([new Assert\NotNull(), new Assert\Type('string'), new Assert\Uuid()])]
        public ?array $giftIds = null,
    ) {
    }
}
