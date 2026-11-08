<?php

declare(strict_types=1);

namespace App\Communities\Application\Command\ScheduleGroupMeetings;

use App\Events\Application\Contract\CommunitySchedule;
use App\Shared\Application\Command;

/** @implements Command<string> the first meeting's id */
final readonly class ScheduleGroupMeetingsCommand implements Command
{
    public function __construct(
        public string $actorId,
        public ?string $requestId,
        public string $communityId,
        public int $expectedVersion,
        public CommunitySchedule $schedule,
        public string $reviewHash,
    ) {
    }
}
