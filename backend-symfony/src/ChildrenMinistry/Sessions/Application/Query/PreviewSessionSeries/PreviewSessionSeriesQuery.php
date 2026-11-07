<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Sessions\Application\Query\PreviewSessionSeries;

use App\ChildrenMinistry\Sessions\Application\SessionSeriesDraft;

final readonly class PreviewSessionSeriesQuery
{
    public function __construct(public SessionSeriesDraft $draft)
    {
    }
}
