<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Sessions\Presentation\Request;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class SetSessionStaffRequest
{
    public function __construct(
        #[Assert\PositiveOrZero]
        public int $expectedVersion,
        #[Assert\NotNull, Assert\Uuid]
        public ?string $sessionId = null,
        #[Assert\NotNull, Assert\Uuid]
        public ?string $workerId = null,
        #[Assert\NotNull, Assert\Choice(choices: ['LEADER', 'ASSISTANT'])]
        public ?string $role = null,
        public bool $present = false,
    ) {
    }
}
