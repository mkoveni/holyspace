<?php

declare(strict_types=1);

namespace App\Volunteering\Presentation\Request;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class MembershipRequest
{
    public function __construct(
        #[Assert\PositiveOrZero]
        public int $expectedVersion,
        #[Assert\NotNull, Assert\Uuid]
        public ?string $volunteerId = null,
        #[Assert\NotNull]
        public ?string $validFrom = null,
        #[Assert\NotNull]
        public ?string $validUntil = null,
    ) {
    }
}
