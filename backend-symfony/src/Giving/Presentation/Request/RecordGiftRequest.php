<?php

declare(strict_types=1);

namespace App\Giving\Presentation\Request;

use Symfony\Component\Validator\Constraints as Assert;

/** Shape checks only, as Java's RecordGiftBody: amounts are whole minor units, 1-20 allocations. */
final readonly class RecordGiftRequest
{
    /** @param list<mixed>|null $allocations */
    public function __construct(
        #[Assert\NotNull]
        #[Assert\Uuid]
        public ?string $requestId = null,
        #[Assert\Uuid]
        public ?string $donorId = null,
        // mixed, not ?int: the serializer would silently truncate 100.9 to 100 for an int property.
        #[Assert\NotNull]
        #[Assert\Type('int')]
        #[Assert\Range(min: 1, max: 1_000_000_000_000)]
        public mixed $amountMinor = null,
        #[Assert\NotBlank]
        public ?string $currency = null,
        #[Assert\NotNull]
        public ?string $receivedOn = null,
        #[Assert\NotBlank]
        public ?string $method = null,
        #[Assert\Length(max: 200)]
        public ?string $externalReference = null,
        #[Assert\NotNull]
        #[Assert\Count(min: 1, max: 20)]
        #[Assert\All([
            new Assert\NotNull(),
            new Assert\Collection(
                fields: [
                    'fundId' => [new Assert\NotNull(), new Assert\Type('string'), new Assert\Uuid()],
                    'amountMinor' => [new Assert\NotNull(), new Assert\Type('int'), new Assert\Range(min: 1, max: 1_000_000_000_000)],
                ],
                allowExtraFields: true,
            ),
        ])]
        public ?array $allocations = null,
    ) {
    }
}
