<?php

declare(strict_types=1);

namespace App\Payments\Presentation\Request;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class ReviewRequest
{
    public function __construct(
        #[Assert\PositiveOrZero] public int $expectedVersion,
        #[Assert\NotNull] #[Assert\Uuid] public ?string $requestId = null,
        #[Assert\NotNull] #[Assert\Uuid] public ?string $chargeId = null,
        #[Assert\NotBlank] #[Assert\Length(max: 200)] public ?string $settlementReference = null,
        #[Assert\NotNull] #[Assert\Type('int')] #[Assert\Range(min: 0, max: 100_000_000)] public mixed $feeMinor = null,
        #[Assert\NotNull] #[Assert\Type('int')] #[Assert\Range(min: 0, max: 100_000_000)] public mixed $bankMinor = null,
        #[Assert\NotBlank] #[Assert\Length(max: 1000)] public ?string $note = null,
    ) {
    }
}
