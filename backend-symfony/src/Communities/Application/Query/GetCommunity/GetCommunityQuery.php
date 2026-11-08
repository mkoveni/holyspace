<?php

declare(strict_types=1);

namespace App\Communities\Application\Query\GetCommunity;

final readonly class GetCommunityQuery
{
    public function __construct(public string $communityId)
    {
    }
}
