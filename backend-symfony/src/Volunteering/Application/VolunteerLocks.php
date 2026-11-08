<?php

declare(strict_types=1);

namespace App\Volunteering\Application;

use App\People\Application\Contract\PersonDirectory;
use App\Volunteering\Application\Contract\LinkedActivities;
use App\Volunteering\Application\Contract\ServingActivity;
use App\Volunteering\Application\Contract\ServingActivityLocks;
use App\Volunteering\Application\Contract\VolunteerReader;
use App\Volunteering\Domain\Repository\VolunteerRepository;
use App\Volunteering\Domain\Volunteer;
use App\Volunteering\Domain\VolunteerException;

/**
 * Java `VolunteerProfiles.lock/lockAll/lockTeamActivities`. Lock order: people sorted by person id, then the linked
 * activities (each originating context locks its own kind, sorted by kind then id), then the volunteer rows. A team
 * change locks its team's linked activities before the team row.
 */
final readonly class VolunteerLocks
{
    /** @param iterable<ServingActivityLocks> $activityLocks */
    public function __construct(
        private VolunteerReader $reader,
        private PersonDirectory $people,
        private LinkedActivities $activities,
        private VolunteerRepository $volunteers,
        private iterable $activityLocks,
    ) {
    }

    public function lock(string $volunteerId): Volunteer
    {
        return $this->lockAll([$volunteerId])[0];
    }

    /**
     * @param list<string>          $volunteerIds
     * @param list<ServingActivity> $extra        further activities the change touches
     *
     * @return list<Volunteer> in person id order
     */
    public function lockAll(array $volunteerIds, array $extra = []): array
    {
        $profiles = [];
        foreach (array_unique($volunteerIds) as $id) {
            $profiles[] = $this->reader->volunteer($id) ?? throw VolunteerException::missing('Volunteer');
        }
        usort($profiles, static fn ($a, $b): int => strcmp($a->personId, $b->personId));
        foreach ($profiles as $profile) {
            $this->people->lock($profile->personId) ?? throw VolunteerException::missing('Person');
        }
        $this->lockActivities([...$this->activities->ofVolunteers(array_values(array_unique($volunteerIds))), ...$extra]);

        return array_map(fn ($profile): Volunteer => $this->volunteers->lock($profile->id) ?? throw VolunteerException::missing('Volunteer'), $profiles);
    }

    public function lockTeamActivities(string $teamId): void
    {
        $this->lockActivities($this->activities->ofTeam($teamId));
    }

    /** @param list<ServingActivity> $activities */
    private function lockActivities(array $activities): void
    {
        $ordered = [];
        foreach ($activities as $activity) {
            $ordered[$activity->kind."\0".$activity->id] = $activity;
        }
        ksort($ordered, \SORT_STRING);
        $ordered = array_values($ordered);
        foreach ($this->activityLocks as $locks) {
            $locks->lock($ordered);
        }
    }
}
