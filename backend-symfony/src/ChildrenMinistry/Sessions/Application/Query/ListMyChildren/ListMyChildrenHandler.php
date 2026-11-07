<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Sessions\Application\Query\ListMyChildren;

use App\ChildrenMinistry\Sessions\Application\FamilyChild;
use App\ChildrenMinistry\Sessions\Application\FamilyPage;
use App\ChildrenMinistry\Sessions\Application\MemberFamilyViews;
use App\ChildrenMinistry\Shared\Domain\ChildrenRules;
use App\IdentityAccess\Application\Contract\MemberIdentity;

final readonly class ListMyChildrenHandler
{
    public function __construct(private MemberIdentity $identity, private MemberFamilyViews $views)
    {
    }

    /** @return FamilyPage<FamilyChild> an unverified, empty page while the member's account has no active link */
    public function handle(ListMyChildrenQuery $query): FamilyPage
    {
        $person = $this->identity->currentPerson();
        if (null === $person) {
            ChildrenRules::page($query->limit, $query->offset);

            return new FamilyPage(false, [], false);
        }

        return $this->views->children($person, $query->limit, $query->offset);
    }
}
