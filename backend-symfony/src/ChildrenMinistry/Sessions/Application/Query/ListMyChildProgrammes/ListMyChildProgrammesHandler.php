<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Sessions\Application\Query\ListMyChildProgrammes;

use App\ChildrenMinistry\Sessions\Application\FamilyPage;
use App\ChildrenMinistry\Sessions\Application\FamilyProgramme;
use App\ChildrenMinistry\Sessions\Application\MemberFamilyViews;
use App\IdentityAccess\Application\Contract\MemberIdentity;

final readonly class ListMyChildProgrammesHandler
{
    public function __construct(private MemberIdentity $identity, private MemberFamilyViews $views)
    {
    }

    /** @return FamilyPage<FamilyProgramme> */
    public function handle(ListMyChildProgrammesQuery $query): FamilyPage
    {
        return $this->views->programmes($this->identity->requirePerson($query->actorId), $query->childId, $query->limit, $query->offset);
    }
}
