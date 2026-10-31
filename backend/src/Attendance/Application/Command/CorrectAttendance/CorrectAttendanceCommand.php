<?php

declare(strict_types=1);

namespace App\Attendance\Application\Command\CorrectAttendance;

use App\Shared\Application\CQRS\Command;

final readonly class CorrectAttendanceCommand implements Command
{
    public function __construct(
        public string $id,
        public string $status,
        public string $reason,
    ) {
    }
}
