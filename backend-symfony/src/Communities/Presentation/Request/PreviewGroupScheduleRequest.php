<?php

declare(strict_types=1);

namespace App\Communities\Presentation\Request;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class PreviewGroupScheduleRequest
{
    public function __construct(
        #[Assert\NotNull, Assert\Valid]
        public ?CommunityScheduleRequest $schedule = null,
    ) {
    }
}
