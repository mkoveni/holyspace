<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Messaging\Application\Query\ListEmailDeliveries;

use App\ChildrenMinistry\Shared\Domain\ChildrenException;
use App\ChildrenMinistry\Shared\Domain\ChildrenRules;
use App\Communications\Application\Contract\DeliveryPage;
use App\Communications\Application\Contract\DeliveryReader;
use App\IdentityAccess\Application\Contract\PermissionChecker;

/** Metadata only. SENT means the mail server accepted it, never that it arrived. */
final readonly class ListEmailDeliveriesHandler
{
    public function __construct(private PermissionChecker $authorization, private DeliveryReader $reader)
    {
    }

    public function handle(ListEmailDeliveriesQuery $query): DeliveryPage
    {
        $this->authorization->requirePermission('children.safeguarding');
        ChildrenRules::page($query->limit, $query->offset);
        if (!\in_array($query->status, ['ALL', 'QUEUED', 'SCHEDULED', 'SENDING', 'SENT', 'DELIVERED', 'FAILED', 'UNKNOWN', 'CANCELLED', 'BLOCKED'], true)) {
            throw ChildrenException::invalid('Choose a delivery status.');
        }

        return $this->reader->listForSource('children-ministry', $query->status, $query->limit, $query->offset);
    }
}
