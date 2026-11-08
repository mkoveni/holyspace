<?php

declare(strict_types=1);

namespace App\Communities\Application\Query\ListPersonCommunities;

use App\Communities\Application\CommunityAccess;
use App\Communities\Application\Contract\CommunityParticipation;
use App\Communities\Application\Contract\ParticipationEntry;
use App\Communities\Domain\CommunityRules;
use App\IdentityAccess\Application\Contract\PermissionChecker;

/** A person's membership periods for the People screen: needs both people.read and ministries.read. */
final readonly class ListPersonCommunitiesHandler
{
    public function __construct(private PermissionChecker $auth, private CommunityParticipation $participation)
    {
    }

    /** @return list<ParticipationEntry> */
    public function handle(ListPersonCommunitiesQuery $query): array
    {
        $this->auth->requirePermission('people.read');
        $this->auth->requirePermission(CommunityAccess::READ);
        CommunityRules::page($query->limit, $query->offset);

        return $this->participation->forPerson($query->personId, $query->limit, $query->offset);
    }
}
