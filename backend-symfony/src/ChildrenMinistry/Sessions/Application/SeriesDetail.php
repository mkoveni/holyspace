<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Sessions\Application;

final readonly class SeriesDetail
{
    /** @param list<ScheduledSession> $sessions */
    public function __construct(public SeriesSummary $series, public array $sessions)
    {
    }
}
