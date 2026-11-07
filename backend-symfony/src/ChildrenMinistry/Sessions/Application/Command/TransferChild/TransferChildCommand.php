<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Sessions\Application\Command\TransferChild;

use App\ChildrenMinistry\Sessions\Application\AttendanceView;
use App\Shared\Application\Command;

/** @implements Command<AttendanceView> */
final readonly class TransferChildCommand implements Command
{
    public function __construct(public string $actorId, public string $attendanceId, public int $expectedVersion, public string $targetSessionId, public ?string $reason)
    {
    }
}
