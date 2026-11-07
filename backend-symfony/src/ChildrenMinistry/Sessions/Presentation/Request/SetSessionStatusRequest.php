<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Sessions\Presentation\Request;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class SetSessionStatusRequest
{
    public function __construct(
        #[Assert\PositiveOrZero]
        public int $expectedVersion,
        #[Assert\NotNull, Assert\Uuid]
        public ?string $sessionId = null,
        #[Assert\NotNull, Assert\Choice(choices: ['PLANNED', 'OPEN', 'NEEDS_REVIEW', 'CLOSED', 'CANCELLED'])]
        public ?string $status = null,
        #[Assert\Length(max: 2000)]
        public ?string $reason = null,
        public ?bool $acknowledgeIncidents = null,
    ) {
    }
}
