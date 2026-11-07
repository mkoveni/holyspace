<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Sessions\Presentation\Request;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class IssuePickupLabelRequest
{
    public function __construct(
        #[Assert\PositiveOrZero]
        public int $expectedVersion,
        #[Assert\NotNull, Assert\Uuid]
        public ?string $attendanceId = null,
        #[Assert\Length(max: 2000)]
        public ?string $replacementReason = null,
    ) {
    }
}
