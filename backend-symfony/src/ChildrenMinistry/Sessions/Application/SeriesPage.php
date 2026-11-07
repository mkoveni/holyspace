<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Sessions\Application;

final readonly class SeriesPage
{
    /** @param list<SeriesSummary> $series */
    public function __construct(public array $series, public int $total)
    {
    }
}
