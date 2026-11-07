<?php

declare(strict_types=1);

namespace App\Events\Application\Command\ScanMemberAttendance;

use App\Shared\Application\Command;

/** @implements Command<string> */
final readonly class ScanMemberAttendanceCommand implements Command
{
    public function __construct(public string $actorId, public string $occurrenceId, public string $requestId, public ?string $credential)
    {
    }

    /** The credential stays out of logs. */
    public function __debugInfo(): array
    {
        return ['occurrenceId' => $this->occurrenceId];
    }
}
