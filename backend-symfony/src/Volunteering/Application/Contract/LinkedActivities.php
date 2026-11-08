<?php

declare(strict_types=1);

namespace App\Volunteering\Application\Contract;

/**
 * The originating activities (for example Children Ministry sessions) whose staffing a change can affect, so they are
 * locked through `ServingActivityLocks` before the change. More than 1000 is a conflict, as in Java.
 */
interface LinkedActivities
{
    /**
     * Activities of the volunteers' non-cancelled assignments.
     *
     * @param list<string> $volunteerIds
     *
     * @return list<ServingActivity>
     */
    public function ofVolunteers(array $volunteerIds): array;

    /** @return list<ServingActivity> activities of the team's linked shifts */
    public function ofTeam(string $teamId): array;
}
