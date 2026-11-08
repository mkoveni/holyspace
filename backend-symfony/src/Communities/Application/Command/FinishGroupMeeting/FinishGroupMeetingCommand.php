<?php

declare(strict_types=1);

namespace App\Communities\Application\Command\FinishGroupMeeting;

use App\Shared\Application\Command;

/** @implements Command<null> */
final readonly class FinishGroupMeetingCommand implements Command
{
    public function __construct(
        public string $actorId,
        public string $meetingId,
        public int $expectedVersion,
        public ?string $status,
        public ?string $reason,
    ) {
    }
}
