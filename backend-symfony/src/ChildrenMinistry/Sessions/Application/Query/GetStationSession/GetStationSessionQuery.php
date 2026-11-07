<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Sessions\Application\Query\GetStationSession;

final readonly class GetStationSessionQuery
{
    public function __construct(public string $actorId, public string $sessionId)
    {
    }
}
