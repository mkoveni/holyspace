<?php

declare(strict_types=1);

namespace App\Volunteering\Application\Query\GetServingTeam;

use App\IdentityAccess\Application\Contract\PermissionChecker;
use App\Volunteering\Application\Contract\TeamDetails;
use App\Volunteering\Application\Contract\TeamDirectory;
use App\Volunteering\Application\ServingPermissions;
use App\Volunteering\Domain\VolunteerException;

final readonly class GetServingTeamHandler
{
    public function __construct(private PermissionChecker $auth, private TeamDirectory $teams)
    {
    }

    public function handle(GetServingTeamQuery $query): TeamDetails
    {
        $this->auth->requirePermission(ServingPermissions::READ);
        $team = $this->teams->team($query->teamId) ?? throw VolunteerException::missing('Team');
        $roles = $this->teams->roles($team->id);
        $approvals = [];
        foreach ($roles as $role) {
            $approvals = [...$approvals, ...$this->teams->approvals($role->id)];
        }

        return new TeamDetails($team, $roles, $this->teams->memberships($team->id), $approvals);
    }
}
