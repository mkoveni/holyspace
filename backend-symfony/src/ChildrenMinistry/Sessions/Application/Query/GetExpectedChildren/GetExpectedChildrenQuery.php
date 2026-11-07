<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Sessions\Application\Query\GetExpectedChildren;

final readonly class GetExpectedChildrenQuery
{
    public function __construct(public string $actorId, public string $sessionId, public int $limit, public int $offset)
    {
    }
}
