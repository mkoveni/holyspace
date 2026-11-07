<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Sessions\Application\Query\ListMyChildren;

final readonly class ListMyChildrenQuery
{
    public function __construct(public int $limit, public int $offset)
    {
    }
}
