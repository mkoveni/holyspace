<?php

declare(strict_types=1);

namespace App\Tests\Volunteering\Application\Roster;

use App\Volunteering\Application\Contract\RoleView;
use App\Volunteering\Application\Contract\TeamReader;
use App\Volunteering\Application\Contract\TeamView;

/** Every team and role exists and is active. */
final readonly class ActiveTeams implements TeamReader
{
    public function team(string $id): TeamView
    {
        return new TeamView($id, true);
    }

    public function role(string $id): RoleView
    {
        return new RoleView($id, 'team', 'NONE', true, []);
    }

    public function memberships(string $teamId, string $volunteerId): array
    {
        return [];
    }

    public function approvals(string $roleId, string $volunteerId): array
    {
        return [];
    }
}
