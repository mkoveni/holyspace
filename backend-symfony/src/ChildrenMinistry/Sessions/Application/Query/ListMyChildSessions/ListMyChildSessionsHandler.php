<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Sessions\Application\Query\ListMyChildSessions;

use App\ChildrenMinistry\Sessions\Application\FamilyPage;
use App\ChildrenMinistry\Sessions\Application\FamilySession;
use App\ChildrenMinistry\Sessions\Application\MemberFamilyViews;
use App\IdentityAccess\Application\Contract\MemberIdentity;

final readonly class ListMyChildSessionsHandler
{
    public function __construct(private MemberIdentity $identity, private MemberFamilyViews $views)
    {
    }

    /** @return FamilyPage<FamilySession> */
    public function handle(ListMyChildSessionsQuery $query): FamilyPage
    {
        return $this->views->sessions($this->identity->requirePerson($query->actorId), $query->childId, $query->limit, $query->offset);
    }
}
