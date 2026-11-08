<?php

declare(strict_types=1);

namespace App\Communities\Application\Query\ListCommunities;

final readonly class ListCommunitiesQuery
{
    public function __construct(public ?string $kind, public ?string $search, public bool $archived, public int $limit, public int $offset)
    {
    }
}
