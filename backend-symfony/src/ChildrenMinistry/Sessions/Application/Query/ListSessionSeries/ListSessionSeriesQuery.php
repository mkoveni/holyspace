<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Sessions\Application\Query\ListSessionSeries;

final readonly class ListSessionSeriesQuery
{
    public function __construct(public int $limit, public int $offset)
    {
    }
}
