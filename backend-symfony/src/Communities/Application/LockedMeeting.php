<?php

declare(strict_types=1);

namespace App\Communities\Application;

use App\Communities\Domain\GroupMeeting;
use App\Events\Application\Contract\CalendarOccurrence;

/** A meeting row and its Events occurrence, both locked for the rest of the command. */
final readonly class LockedMeeting
{
    public function __construct(public GroupMeeting $meeting, public CalendarOccurrence $date)
    {
    }
}
