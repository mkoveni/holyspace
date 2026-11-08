<?php

declare(strict_types=1);

namespace App\Communities\Application\Query\ListPersonCommunities;

final readonly class ListPersonCommunitiesQuery
{
    public function __construct(public string $personId, public int $limit, public int $offset)
    {
    }
}
