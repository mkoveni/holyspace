<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Messaging\Application\Query\ListEmailDeliveries;

final readonly class ListEmailDeliveriesQuery
{
    public function __construct(public string $status, public int $limit, public int $offset)
    {
    }
}
