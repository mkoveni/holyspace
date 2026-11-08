<?php

declare(strict_types=1);

namespace App\Communities\Application\Query\ListCommunityMembers;

use App\Communities\Application\CommunityAccess;
use App\Communities\Application\Contract\CommunityReader;
use App\Communities\Application\Contract\MemberPage;
use App\Communities\Domain\CommunityRules;

/** Members need manage, or current leadership of this community; directory readers get no rosters. */
final readonly class ListCommunityMembersHandler
{
    public function __construct(private CommunityAccess $access, private CommunityReader $reader)
    {
    }

    public function handle(ListCommunityMembersQuery $query): MemberPage
    {
        $this->access->directoryScope();
        $this->access->require($query->communityId);
        CommunityRules::page($query->limit, $query->offset);

        return new MemberPage(
            $this->reader->members($query->communityId, $query->ended, $query->limit, $query->offset),
            $this->reader->memberCount($query->communityId, $query->ended),
        );
    }
}
