<?php

declare(strict_types=1);

namespace App\Communities\Presentation\Request;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class AppointCommunityLeaderRequest
{
    public function __construct(
        #[Assert\PositiveOrZero]
        public int $expectedVersion,
        #[Assert\NotNull, Assert\Uuid]
        public ?string $requestId = null,
        #[Assert\NotBlank(normalizer: 'trim'), Assert\Length(max: 150)]
        public ?string $role = null,
        #[Assert\Uuid]
        public ?string $accountId = null,
        #[Assert\NotBlank(normalizer: 'trim'), Assert\Length(max: 2000)]
        public ?string $reason = null,
    ) {
    }
}
