<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Sessions\Application\Query\GetMyChild;

use App\ChildrenMinistry\Sessions\Application\FamilyChild;
use App\ChildrenMinistry\Sessions\Application\MemberFamilyViews;
use App\IdentityAccess\Application\Contract\MemberIdentity;

final readonly class GetMyChildHandler
{
    public function __construct(private MemberIdentity $identity, private MemberFamilyViews $views)
    {
    }

    public function handle(GetMyChildQuery $query): FamilyChild
    {
        return $this->views->require($this->identity->requirePerson($query->actorId), $query->childId);
    }
}
