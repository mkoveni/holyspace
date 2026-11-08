<?php

declare(strict_types=1);

namespace App\Communities\Application\Query\GetCommunityHistory;

final readonly class GetCommunityHistoryQuery
{
    public function __construct(public string $communityId, public int $limit, public int $offset)
    {
    }
}
