<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Participation\Presentation\Request;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class CreateClassRequest
{
    public function __construct(
        #[Assert\NotNull, Assert\Uuid]
        public ?string $programmeId = null,
        public string $name = '',
        public int $minAgeMonths = 0,
        public int $maxAgeMonths = 0,
    ) {
    }
}
