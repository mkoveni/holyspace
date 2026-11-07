<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Incidents\Application\Query\ListIncidentAttendance;

final readonly class ListIncidentAttendanceQuery
{
    public function __construct(public string $actorId, public string $sessionId, public string $search, public int $limit, public int $offset)
    {
    }
}
