<?php

declare(strict_types=1);

namespace App\Attendance\Presentation\Http\DTO;

final readonly class AttendanceFilter
{
    public function __construct(public ?string $personId = null, public ?string $occurrenceId = null)
    {
    }
}
