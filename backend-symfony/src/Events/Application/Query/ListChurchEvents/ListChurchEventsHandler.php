<?php

declare(strict_types=1);

namespace App\Events\Application\Query\ListChurchEvents;

use App\Events\Application\Contract\EventPage;
use App\Events\Application\Contract\EventReader;
use App\Events\Domain\EventRules;
use App\IdentityAccess\Application\Contract\PermissionChecker;

final readonly class ListChurchEventsHandler
{
    public function __construct(private PermissionChecker $authorization, private EventReader $events)
    {
    }

    public function handle(ListChurchEventsQuery $query): EventPage
    {
        $this->authorization->requirePermission('events.read');
        EventRules::page($query->limit, $query->offset);

        return $this->events->search(EventRules::optional($query->search, 200), $query->includeArchived, $query->limit, $query->offset);
    }
}
