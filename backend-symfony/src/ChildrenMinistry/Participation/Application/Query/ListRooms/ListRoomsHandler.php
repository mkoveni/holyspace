<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Participation\Application\Query\ListRooms;

use App\ChildrenMinistry\Participation\Application\Contract\VenueReader;
use App\ChildrenMinistry\Participation\Application\RoomView;
use App\IdentityAccess\Application\Contract\PermissionChecker;

final readonly class ListRoomsHandler
{
    public function __construct(
        private PermissionChecker $authorization,
        private VenueReader $venues,
    ) {
    }

    /** @return list<RoomView> */
    public function handle(ListRoomsQuery $query): array
    {
        $this->authorization->requirePermission('children.read');

        return null === $query->locationId ? [] : $this->venues->rooms($query->locationId);
    }
}
