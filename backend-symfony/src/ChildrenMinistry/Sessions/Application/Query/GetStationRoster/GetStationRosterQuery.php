<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Sessions\Application\Query\GetStationRoster;

final readonly class GetStationRosterQuery
{
    public function __construct(public string $actorId, public string $sessionId, public int $limit, public int $offset, public bool $openOnly)
    {
    }
}
