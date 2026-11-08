<?php

declare(strict_types=1);

namespace App\Communities\Application\Query\GetMyCommunity;

final readonly class GetMyCommunityQuery
{
    public function __construct(public string $communityId, public int $leaderLimit, public int $leaderOffset)
    {
    }
}
