<?php

declare(strict_types=1);

namespace App\Communities\Application\Query\ListCommunityMembers;

final readonly class ListCommunityMembersQuery
{
    public function __construct(public string $communityId, public bool $ended, public int $limit, public int $offset)
    {
    }
}
