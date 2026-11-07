<?php

declare(strict_types=1);

namespace App\Events\Application\Query\GetEventAttendanceHistory;

final readonly class GetEventAttendanceHistoryQuery
{
    public function __construct(public string $registrationId, public int $limit, public int $offset)
    {
    }
}
