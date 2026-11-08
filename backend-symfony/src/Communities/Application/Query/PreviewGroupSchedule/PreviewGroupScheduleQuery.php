<?php

declare(strict_types=1);

namespace App\Communities\Application\Query\PreviewGroupSchedule;

use App\Events\Application\Contract\CommunitySchedule;

final readonly class PreviewGroupScheduleQuery
{
    public function __construct(public string $communityId, public CommunitySchedule $schedule)
    {
    }
}
