<?php

declare(strict_types=1);

namespace App\Communities\Presentation\Request;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class ScheduleGroupMeetingsRequest
{
    public function __construct(
        #[Assert\PositiveOrZero]
        public int $expectedVersion,
        #[Assert\NotNull, Assert\Uuid]
        public ?string $requestId = null,
        #[Assert\NotNull, Assert\Valid]
        public ?CommunityScheduleRequest $schedule = null,
        #[Assert\NotBlank(normalizer: 'trim'), Assert\Length(max: 150)]
        public ?string $reviewHash = null,
    ) {
    }
}
