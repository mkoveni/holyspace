<?php

declare(strict_types=1);

namespace App\Events\Application\Query\ListEventAttendance;

final readonly class ListEventAttendanceQuery
{
    public function __construct(public string $occurrenceId, public string $search, public ?string $status, public int $limit, public int $offset)
    {
    }
}
