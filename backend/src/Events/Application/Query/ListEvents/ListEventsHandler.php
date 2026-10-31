<?php

declare(strict_types=1);

namespace App\Events\Application\Query\ListEvents;

use App\Events\Application\DTO\EventView;
use App\Events\Application\ReadModel\EventReadStore;

final readonly class ListEventsHandler
{
    public function __construct(private EventReadStore $events)
    {
    }

    public function __invoke(ListEventsQuery $message): array
    {
        return array_map(EventView::fromDomain(...), $this->events->all());
    }
}
