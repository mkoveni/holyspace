<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Sessions\Infrastructure;

use App\ChildrenMinistry\Sessions\Domain\Repository\SessionRepository;
use App\Volunteering\Application\Contract\ServingActivity;
use App\Volunteering\Application\Contract\ServingActivityLocks;

/** Locks session rows so staffing changes and attendance decisions cannot interleave. */
final readonly class ServingActivityGuard implements ServingActivityLocks
{
    public function __construct(private SessionRepository $sessions)
    {
    }

    /** @param list<ServingActivity> $activities */
    public function lock(array $activities): void
    {
        $ids = [];
        foreach ($activities as $activity) {
            if (ServingSessionActivities::KIND === $activity->kind) {
                $ids[$activity->id] = $activity->id;
            }
        }
        $ids = array_values($ids);
        sort($ids, \SORT_STRING);
        foreach ($ids as $id) {
            $this->sessions->ministrySessionForUpdate($id);
        }
    }
}
