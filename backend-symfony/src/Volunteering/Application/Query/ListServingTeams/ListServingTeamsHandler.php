<?php

declare(strict_types=1);

namespace App\Volunteering\Application\Query\ListServingTeams;

use App\IdentityAccess\Application\Contract\PermissionChecker;
use App\Volunteering\Application\Contract\TeamDirectory;
use App\Volunteering\Application\Contract\TeamPage;
use App\Volunteering\Application\ServingPermissions;
use App\Volunteering\Application\ServingViews;

final readonly class ListServingTeamsHandler
{
    public function __construct(private PermissionChecker $auth, private TeamDirectory $teams)
    {
    }

    public function handle(ListServingTeamsQuery $query): TeamPage
    {
        $this->auth->requirePermission(ServingPermissions::READ);
        ServingViews::page($query->limit, $query->offset);

        return new TeamPage($this->teams->teams($query->limit, $query->offset), $this->teams->teamCount());
    }
}
