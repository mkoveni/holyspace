<?php

declare(strict_types=1);

namespace App\Giving\Presentation\Request;

use Symfony\Component\Validator\Constraints as Assert;

/** Java's CancelBody, shared by pledge and assignment cancellation. */
final readonly class CancelPledgeRequest
{
    public function __construct(
        #[Assert\PositiveOrZero]
        public int $expectedVersion,
        #[Assert\NotBlank]
        #[Assert\Length(max: 1000)]
        public ?string $reason = null,
    ) {
    }
}
