<?php

declare(strict_types=1);

namespace App\Communities\Application\Query\ListMyCommunities;

final readonly class ListMyCommunitiesQuery
{
    public function __construct(public ?string $kind, public int $limit, public int $offset)
    {
    }
}
