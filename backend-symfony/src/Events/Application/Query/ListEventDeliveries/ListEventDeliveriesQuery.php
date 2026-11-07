<?php

declare(strict_types=1);

namespace App\Events\Application\Query\ListEventDeliveries;

final readonly class ListEventDeliveriesQuery
{
    public function __construct(public ?string $status, public int $limit, public int $offset)
    {
    }
}
