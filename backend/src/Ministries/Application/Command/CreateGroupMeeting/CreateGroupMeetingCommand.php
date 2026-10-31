<?php

declare(strict_types=1);

namespace App\Ministries\Application\Command\CreateGroupMeeting;

use App\Shared\Application\CQRS\Command;

final readonly class CreateGroupMeetingCommand implements Command
{
    public function __construct(public string $id, public string $occurrenceId)
    {
    }
}
