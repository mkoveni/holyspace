<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Participation\Application\Query\ListLocations;

use App\ChildrenMinistry\Participation\Application\Contract\VenueReader;
use App\ChildrenMinistry\Participation\Application\LocationView;
use App\ChildrenMinistry\Shared\Domain\ChildrenRules;
use App\IdentityAccess\Application\Contract\PermissionChecker;

final readonly class ListLocationsHandler
{
    public function __construct(
        private PermissionChecker $authorization,
        private VenueReader $venues,
    ) {
    }

    /** @return list<LocationView> */
    public function handle(ListLocationsQuery $query): array
    {
        $this->authorization->requirePermission('children.read');
        ChildrenRules::page($query->limit, $query->offset);

        return $this->venues->locations($query->limit, $query->offset);
    }
}
