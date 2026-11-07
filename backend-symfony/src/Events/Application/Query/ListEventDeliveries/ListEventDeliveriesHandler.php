<?php

declare(strict_types=1);

namespace App\Events\Application\Query\ListEventDeliveries;

use App\Communications\Application\Contract\DeliveryPage;
use App\Communications\Application\Contract\DeliveryReader;
use App\Events\Domain\EventRules;
use App\IdentityAccess\Application\Contract\PermissionChecker;

final readonly class ListEventDeliveriesHandler
{
    public function __construct(private PermissionChecker $authorization, private DeliveryReader $deliveries)
    {
    }

    public function handle(ListEventDeliveriesQuery $query): DeliveryPage
    {
        $this->authorization->requirePermission('events.communications.send');
        EventRules::page($query->limit, $query->offset);

        return $this->deliveries->listForSource('events', $query->status, $query->limit, $query->offset);
    }
}
