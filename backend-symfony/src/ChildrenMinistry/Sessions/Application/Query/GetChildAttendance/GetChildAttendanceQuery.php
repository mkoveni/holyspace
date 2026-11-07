<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Sessions\Application\Query\GetChildAttendance;

final readonly class GetChildAttendanceQuery
{
    public function __construct(public string $childId, public int $limit, public int $offset)
    {
    }
}
