<?php

declare(strict_types=1);

namespace App\Events\Application\Query\GetEvent;

use App\Events\Application\DTO\EventView;
use App\Events\Application\ReadModel\EventReadStore;
use App\Events\Domain\ValueObject\EventId;
use OutOfBoundsException;

final readonly class GetEventHandler
{
    public function __construct(private EventReadStore $events)
    {
    }

    public function __invoke(GetEventQuery $message): EventView
    {
        $id = $message->id;

        return EventView::fromDomain($this->events->find(EventId::fromString($id)) ?? throw new OutOfBoundsException('Event not found.'));
    }
}
