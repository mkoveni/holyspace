<?php

declare(strict_types=1);

namespace App\Volunteering\Application;

use App\Volunteering\Domain\Repository\TeamRepository;
use App\Volunteering\Domain\ServingRules;
use App\Volunteering\Domain\Team;
use App\Volunteering\Domain\VolunteerException;

/** Java `TeamManagement.lock`: the team row at the expected version, optionally after its linked activities. */
final readonly class TeamLocks
{
    public function __construct(private TeamRepository $teams, private VolunteerLocks $volunteers)
    {
    }

    public function team(string $teamId, int $expectedVersion): Team
    {
        $team = $this->teams->lock($teamId) ?? throw VolunteerException::missing('Team');
        ServingRules::version($team->version, $expectedVersion);

        return $team;
    }

    /** Team and role changes alter readiness, so the team's linked activities are locked first. */
    public function withActivities(string $teamId, int $expectedVersion): Team
    {
        $this->volunteers->lockTeamActivities($teamId);

        return $this->team($teamId, $expectedVersion);
    }
}
