<?php

declare(strict_types=1);

namespace App\Attendance\Application\Command\RecordAttendance;

use App\Shared\Application\CQRS\Command;

final readonly class RecordAttendanceCommand implements Command
{
    public function __construct(public array $data)
    {
    }
}
