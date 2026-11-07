<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Messaging\Application\Query\GetEmailDelivery;

use App\ChildrenMinistry\Messaging\Application\EmailDeliveryView;
use App\ChildrenMinistry\Shared\Domain\ChildrenException;
use App\ChildrenMinistry\Shared\Domain\ChildrenRules;
use App\Communications\Application\Contract\DeliveryReader;
use App\IdentityAccess\Application\Contract\PermissionChecker;

final readonly class GetEmailDeliveryHandler
{
    public function __construct(private PermissionChecker $authorization, private DeliveryReader $reader)
    {
    }

    public function handle(GetEmailDeliveryQuery $query): EmailDeliveryView
    {
        $this->authorization->requirePermission('children.safeguarding');
        ChildrenRules::page($query->limit, $query->offset);
        $delivery = $this->reader->delivery($query->deliveryId);
        if (null === $delivery || !$this->reader->belongsTo($delivery->id, 'children-ministry')) {
            throw ChildrenException::missing('Delivery');
        }

        return new EmailDeliveryView($delivery, $this->reader->events($delivery->id, $query->limit, $query->offset));
    }
}
