<?php

declare(strict_types=1);

namespace App\Communities\Application\Query\ListCommunityFollowUps;

final readonly class ListCommunityFollowUpsQuery
{
    public function __construct(public string $communityId, public bool $completed, public int $limit, public int $offset)
    {
    }
}
