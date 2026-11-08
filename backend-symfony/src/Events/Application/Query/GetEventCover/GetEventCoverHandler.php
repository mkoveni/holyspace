<?php

declare(strict_types=1);

namespace App\Events\Application\Query\GetEventCover;

use App\Events\Application\Contract\EventCover;
use App\Events\Application\Contract\EventCoverReader;
use App\Events\Application\Contract\EventReader;
use App\Events\Domain\EventException;
use App\IdentityAccess\Application\Contract\PermissionChecker;
use App\Shared\Application\CoverImage\CoverImageContent;
use App\Shared\Application\CoverImage\CoverImages;
use App\Shared\Domain\CoverSlot;

final readonly class GetEventCoverHandler
{
    public function __construct(
        private PermissionChecker $authorization,
        private EventReader $events,
        private EventCoverReader $covers,
        private CoverImages $images,
    ) {
    }

    public function handle(GetEventCoverQuery $query): EventCover
    {
        $slot = $this->slot($query);

        return new EventCover($slot, $this->images->info($slot));
    }

    public function content(GetEventCoverQuery $query): CoverImageContent
    {
        return $this->images->content($this->slot($query)) ?? throw EventException::missing('Cover image');
    }

    private function slot(GetEventCoverQuery $query): CoverSlot
    {
        $this->authorization->requirePermission('events.read');
        $this->events->find($query->eventId) ?? throw EventException::missing('Event');

        return $this->covers->cover($query->eventId);
    }
}
