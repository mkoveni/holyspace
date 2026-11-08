<?php

declare(strict_types=1);

namespace App\Communities\Application\Query\ListCommunityLeadership;

final readonly class ListCommunityLeadershipQuery
{
    public function __construct(public string $communityId, public int $limit, public int $offset)
    {
    }
}
