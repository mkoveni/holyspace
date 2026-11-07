<?php

declare(strict_types=1);

namespace App\Giving\Presentation\Request;

use Symfony\Component\Validator\Constraints as Assert;

/** Java's ReviewBody. */
final readonly class ReviewReconciliationRequest
{
    public function __construct(
        #[Assert\PositiveOrZero]
        public int $expectedVersion,
        #[Assert\NotNull]
        #[Assert\Uuid]
        public ?string $requestId = null,
        #[Assert\NotBlank]
        public ?string $outcome = null,
        #[Assert\NotBlank]
        #[Assert\Length(max: 1000)]
        public ?string $note = null,
    ) {
    }
}
