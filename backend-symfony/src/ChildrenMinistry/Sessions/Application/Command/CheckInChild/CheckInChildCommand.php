<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Sessions\Application\Command\CheckInChild;

use App\ChildrenMinistry\Sessions\Application\AttendanceView;
use App\Shared\Application\Command;

/** @implements Command<AttendanceView> */
final readonly class CheckInChildCommand implements Command
{
    public function __construct(public string $actorId, public string $sessionId, public string $childId, public string $requestId)
    {
    }
}
