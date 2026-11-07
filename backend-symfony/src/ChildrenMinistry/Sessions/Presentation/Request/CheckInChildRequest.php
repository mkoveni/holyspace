<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Sessions\Presentation\Request;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class CheckInChildRequest
{
    public function __construct(
        #[Assert\NotNull, Assert\Uuid]
        public ?string $sessionId = null,
        #[Assert\NotNull, Assert\Uuid]
        public ?string $childId = null,
        #[Assert\NotNull, Assert\Uuid]
        public ?string $requestId = null,
    ) {
    }
}
