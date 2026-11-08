<?php

declare(strict_types=1);

namespace App\Communities\Application\Query\ListCommunityFollowUps;

use App\Communities\Application\CommunityAccess;
use App\Communities\Application\Contract\CommunityReader;
use App\Communities\Application\Contract\FollowUpView;
use App\Communities\Domain\CommunityRules;

/** Open follow-ups by due date (completed ones too when asked), for managers and this community's leaders. */
final readonly class ListCommunityFollowUpsHandler
{
    public function __construct(private CommunityAccess $access, private CommunityReader $reader)
    {
    }

    /** @return list<FollowUpView> */
    public function handle(ListCommunityFollowUpsQuery $query): array
    {
        $this->access->directoryScope();
        $this->access->require($query->communityId);
        CommunityRules::page($query->limit, $query->offset);

        return $this->reader->followUps($query->communityId, $query->completed, $query->limit, $query->offset);
    }
}
