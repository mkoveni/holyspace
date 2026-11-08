<?php

declare(strict_types=1);

namespace App\Volunteering\Application\Contract;

/** Java `ServingViews.TeamDetails`: the team, its roles, every membership and every approval of its roles. */
final readonly class TeamDetails
{
    /**
     * @param list<ServingRoleRecord> $roles
     * @param list<ServingPeriod>     $memberships scope = team
     * @param list<ServingPeriod>     $approvals   scope = role
     */
    public function __construct(public TeamRecord $team, public array $roles, public array $memberships, public array $approvals)
    {
    }
}
