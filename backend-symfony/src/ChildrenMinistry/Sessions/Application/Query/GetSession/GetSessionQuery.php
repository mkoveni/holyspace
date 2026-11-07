<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Sessions\Application\Query\GetSession;

final readonly class GetSessionQuery
{
    public function __construct(public string $sessionId)
    {
    }
}
