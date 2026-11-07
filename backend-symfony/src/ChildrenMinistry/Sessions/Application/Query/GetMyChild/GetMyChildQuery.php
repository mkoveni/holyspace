<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Sessions\Application\Query\GetMyChild;

final readonly class GetMyChildQuery
{
    public function __construct(public string $actorId, public string $childId)
    {
    }
}
