<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Sessions\Application\Query\GetRoster;

final readonly class GetRosterQuery
{
    public function __construct(public string $actorId, public string $sessionId, public bool $openOnly, public int $limit, public int $offset)
    {
    }
}
