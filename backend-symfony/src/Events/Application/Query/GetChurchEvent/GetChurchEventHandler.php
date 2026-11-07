<?php

declare(strict_types=1);

namespace App\Events\Application\Query\GetChurchEvent;

use App\Events\Application\Contract\EventDetails;
use App\Events\Application\Contract\EventReader;
use App\Events\Domain\EventException;
use App\Events\Domain\EventRules;
use App\IdentityAccess\Application\Contract\PermissionChecker;

final readonly class GetChurchEventHandler
{
    public function __construct(private PermissionChecker $authorization, private EventReader $events)
    {
    }

    public function handle(GetChurchEventQuery $query): EventDetails
    {
        $this->authorization->requirePermission('events.read');
        EventRules::page($query->limit, $query->offset);
        $event = $this->events->find($query->eventId) ?? throw EventException::missing('Event');

        return new EventDetails($event, $this->events->seriesFor($event->id, $query->limit, $query->offset), $this->events->seriesCount($event->id));
    }
}
