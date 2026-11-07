<?php

declare(strict_types=1);

namespace App\Events\Application\Query\GetMemberOffer;

use App\Events\Application\Contract\EventReader;
use App\Events\Application\Contract\MemberEventReader;
use App\Events\Application\Contract\MemberOffer;
use App\Events\Domain\EventException;
use App\IdentityAccess\Application\Contract\PermissionChecker;

final readonly class GetMemberOfferHandler
{
    public function __construct(private PermissionChecker $authorization, private MemberEventReader $reader, private EventReader $events)
    {
    }

    public function handle(GetMemberOfferQuery $query): MemberOffer
    {
        $this->authorization->requirePermission('events.manage');
        $this->events->find($query->eventId) ?? throw EventException::missing('Event');

        return $this->reader->offer($query->eventId);
    }
}
