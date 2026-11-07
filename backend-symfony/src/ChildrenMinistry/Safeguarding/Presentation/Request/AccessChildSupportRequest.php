<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Safeguarding\Presentation\Request;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class AccessChildSupportRequest
{
    public function __construct(
        #[Assert\NotNull, Assert\Uuid]
        public ?string $childId = null,
        #[Assert\Uuid]
        public ?string $sessionId = null,
    ) {
    }
}
