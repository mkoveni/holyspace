<?php

declare(strict_types=1);

namespace App\Communities\Application\Command\RecordGroupAttendance;

use App\Shared\Application\Command;

/** @implements Command<null> */
final readonly class RecordGroupAttendanceCommand implements Command
{
    public function __construct(
        public string $actorId,
        public string $rosterId,
        public int $expectedVersion,
        public ?string $status,
        public ?string $reason,
    ) {
    }
}
