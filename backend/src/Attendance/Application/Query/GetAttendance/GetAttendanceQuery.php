<?php

declare(strict_types=1);

namespace App\Attendance\Application\Query\GetAttendance;

use App\Shared\Application\CQRS\Query;

final readonly class GetAttendanceQuery implements Query
{
    public function __construct(public ?string $personId = null, public ?string $occurrenceId = null)
    {
    }
}
