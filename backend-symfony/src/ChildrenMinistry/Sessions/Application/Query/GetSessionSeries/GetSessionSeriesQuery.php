<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Sessions\Application\Query\GetSessionSeries;

final readonly class GetSessionSeriesQuery
{
    public function __construct(public string $id)
    {
    }
}
