<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Sessions\Application\Query\GetSessionDay;

final readonly class GetSessionDayQuery
{
    public function __construct(public string $actorId, public string $sessionId, public string $search, public string $view, public int $limit, public int $offset)
    {
    }
}
