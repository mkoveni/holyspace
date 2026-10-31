<?php

declare(strict_types=1);

namespace App\ServicePlanning\Application\Query\GetOwnPublishedPlans;

use App\ServicePlanning\Application\ReadModel\ServicePlanningReadStore;
use App\Volunteering\Application\Query\GetOwnVolunteerSchedule\GetOwnVolunteerScheduleHandler;
use App\Volunteering\Application\Query\GetOwnVolunteerSchedule\GetOwnVolunteerScheduleQuery;

final readonly class GetOwnPublishedPlansHandler
{
    public function __construct(private ServicePlanningReadStore $records, private GetOwnVolunteerScheduleHandler $getOwnVolunteerScheduleHandler)
    {
    }

    public function __invoke(GetOwnPublishedPlansQuery $message): array
    {
        // Reuse the serving identity check and invitation expiry handling.
        $schedule = ($this->getOwnVolunteerScheduleHandler)(new GetOwnVolunteerScheduleQuery());
        $occurrences = [];
        foreach ($schedule['items'] as $item) {
            if (in_array($item['assignment']->status, ['invited', 'assigned', 'needs_review'], true) && $item['occurrence'] && 'cancelled' !== $item['occurrence']['status']) {
                $occurrences[] = $item['occurrence']['id'];
            }
        }

        return ['items' => array_values(array_filter($this->records->all('plans'), fn ($p) => 'published' === $p->status && in_array($p->occurrenceId, $occurrences, true)))];
    }
}
