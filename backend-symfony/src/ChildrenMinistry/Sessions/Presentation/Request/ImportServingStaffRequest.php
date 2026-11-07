<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Sessions\Presentation\Request;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class ImportServingStaffRequest
{
    public function __construct(
        #[Assert\PositiveOrZero]
        public int $expectedVersion,
        #[Assert\NotNull, Assert\Uuid]
        public ?string $assignmentId = null,
        #[Assert\NotBlank]
        public ?string $responsibility = null,
        public bool $present = false,
    ) {
    }
}
