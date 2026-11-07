<?php

declare(strict_types=1);

namespace App\Events\Application\Query\ListEventVenues;

use App\Events\Application\Contract\VenuePage;
use App\Events\Application\Contract\VenueReader;
use App\Events\Domain\EventRules;
use App\IdentityAccess\Application\Contract\PermissionChecker;

final readonly class ListEventVenuesHandler
{
    public function __construct(
        private PermissionChecker $authorization,
        private VenueReader $venues,
    ) {
    }

    public function handle(ListEventVenuesQuery $query): VenuePage
    {
        $this->authorization->requirePermission('events.read');
        EventRules::page($query->limit, $query->offset);

        return $this->venues->search(
            EventRules::optional($query->search, 150),
            $query->includeArchived,
            $query->limit,
            $query->offset,
        );
    }
}
