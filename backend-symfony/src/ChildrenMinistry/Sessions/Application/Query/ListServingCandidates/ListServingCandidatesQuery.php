<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Sessions\Application\Query\ListServingCandidates;

final readonly class ListServingCandidatesQuery
{
    public function __construct(public string $actorId, public string $sessionId)
    {
    }
}
