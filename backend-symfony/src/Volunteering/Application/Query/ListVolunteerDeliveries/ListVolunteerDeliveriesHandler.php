<?php

declare(strict_types=1);

namespace App\Volunteering\Application\Query\ListVolunteerDeliveries;

use App\Communications\Application\Contract\DeliveryPage;
use App\Communications\Application\Contract\DeliveryReader;
use App\IdentityAccess\Application\Contract\PermissionChecker;
use App\Volunteering\Application\ServingPermissions;
use App\Volunteering\Application\ServingViews;

final readonly class ListVolunteerDeliveriesHandler
{
    public function __construct(
        private PermissionChecker $auth,
        private DeliveryReader $deliveries,
    ) {
    }

    public function handle(ListVolunteerDeliveriesQuery $query): DeliveryPage
    {
        $this->auth->requirePermission(ServingPermissions::COMMUNICATIONS);
        ServingViews::page($query->limit, $query->offset);

        return $this->deliveries->listForSource('volunteering', $query->status, $query->limit, $query->offset);
    }
}
