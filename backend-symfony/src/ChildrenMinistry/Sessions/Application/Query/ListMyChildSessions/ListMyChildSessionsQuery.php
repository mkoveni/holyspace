<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Sessions\Application\Query\ListMyChildSessions;

final readonly class ListMyChildSessionsQuery
{
    public function __construct(public string $actorId, public string $childId, public int $limit, public int $offset)
    {
    }
}
