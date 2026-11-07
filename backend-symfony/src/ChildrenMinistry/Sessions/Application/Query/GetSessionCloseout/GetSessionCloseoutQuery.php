<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Sessions\Application\Query\GetSessionCloseout;

final readonly class GetSessionCloseoutQuery
{
    public function __construct(public string $actorId, public string $sessionId)
    {
    }
}
