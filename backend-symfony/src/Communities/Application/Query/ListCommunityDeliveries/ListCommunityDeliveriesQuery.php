<?php

declare(strict_types=1);

namespace App\Communities\Application\Query\ListCommunityDeliveries;

final readonly class ListCommunityDeliveriesQuery
{
    public function __construct(public string $communityId, public int $limit, public int $offset)
    {
    }
}
