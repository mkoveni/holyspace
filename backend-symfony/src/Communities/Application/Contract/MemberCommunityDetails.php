<?php

declare(strict_types=1);

namespace App\Communities\Application\Contract;

final readonly class MemberCommunityDetails
{
    /** @param list<MemberCommunityLeader> $leaders */
    public function __construct(public MemberCommunity $community, public array $leaders, public bool $hasMoreLeaders)
    {
    }
}
