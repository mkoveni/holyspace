<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Messaging\Application\Query\GetEmailDelivery;

final readonly class GetEmailDeliveryQuery
{
    public function __construct(public string $deliveryId, public int $limit, public int $offset)
    {
    }
}
