<?php

declare(strict_types=1);

namespace App\Events\Application\Contract;

final readonly class EventDetails
{
    /** @param list<SeriesView> $series */
    public function __construct(public EventView $event, public array $series, public int $totalSeries)
    {
    }
}
