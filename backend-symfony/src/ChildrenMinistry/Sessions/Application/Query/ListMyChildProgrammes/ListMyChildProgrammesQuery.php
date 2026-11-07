<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Sessions\Application\Query\ListMyChildProgrammes;

final readonly class ListMyChildProgrammesQuery
{
    public function __construct(public string $actorId, public string $childId, public int $limit, public int $offset)
    {
    }
}
