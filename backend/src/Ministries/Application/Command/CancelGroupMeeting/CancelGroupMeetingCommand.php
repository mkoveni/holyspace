<?php

declare(strict_types=1);

namespace App\Ministries\Application\Command\CancelGroupMeeting;

use App\Shared\Application\CQRS\Command;

final readonly class CancelGroupMeetingCommand implements Command
{
    public function __construct(
        public string $groupId,
        public string $id,
        public int $version,
        public string $reason,
    ) {
    }
}
