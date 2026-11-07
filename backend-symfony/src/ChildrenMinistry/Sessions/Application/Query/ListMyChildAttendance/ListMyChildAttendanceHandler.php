<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Sessions\Application\Query\ListMyChildAttendance;

use App\ChildrenMinistry\Sessions\Application\FamilyPage;
use App\ChildrenMinistry\Sessions\Application\FamilyVisit;
use App\ChildrenMinistry\Sessions\Application\MemberFamilyViews;
use App\IdentityAccess\Application\Contract\MemberIdentity;

final readonly class ListMyChildAttendanceHandler
{
    public function __construct(private MemberIdentity $identity, private MemberFamilyViews $views)
    {
    }

    /** @return FamilyPage<FamilyVisit> */
    public function handle(ListMyChildAttendanceQuery $query): FamilyPage
    {
        return $this->views->attendance($this->identity->requirePerson($query->actorId), $query->childId, $query->limit, $query->offset);
    }
}
