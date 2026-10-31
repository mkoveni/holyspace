<?php

declare(strict_types=1);

namespace App\Volunteering\Application\Query\GetVolunteeringOverview;

use App\Volunteering\Application\ReadModel\VolunteeringReadStore;

final readonly class GetVolunteeringOverviewHandler
{
    public function __construct(private VolunteeringReadStore $records)
    {
    }

    public function __invoke(GetVolunteeringOverviewQuery $message): array
    {
        $shifts = [];
        $assignments = $this->records->all('assignments');
        foreach ($this->records->all('shifts') as $shift) {
            $filled = count(array_filter($assignments, fn ($a) => $a->shiftId === $shift->id && in_array($a->status, ['invited', 'assigned', 'needs_review'], true)));
            $shifts[] = get_object_vars($shift) + ['vacancies' => max(0, $shift->positions - $filled)];
        }

        return ['roles' => $this->records->all('roles'), 'profiles' => $this->records->all('profiles'), 'shifts' => $shifts, 'assignments' => $assignments];
    }
}
