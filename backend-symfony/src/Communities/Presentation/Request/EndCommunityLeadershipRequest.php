<?php

declare(strict_types=1);

namespace App\Communities\Presentation\Request;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class EndCommunityLeadershipRequest
{
    public function __construct(
        #[Assert\PositiveOrZero]
        public int $expectedVersion,
        #[Assert\NotBlank(normalizer: 'trim'), Assert\Length(max: 2000)]
        public ?string $reason = null,
    ) {
    }
}
