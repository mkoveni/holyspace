<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Participation\Presentation\Request;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class EnrolChildRequest
{
    public function __construct(
        #[Assert\NotNull, Assert\Uuid]
        public ?string $programmeId = null,
        #[Assert\NotNull, Assert\Uuid]
        public ?string $childId = null,
        public string $validFrom = '',
        public string $validUntil = '',
    ) {
    }
}
