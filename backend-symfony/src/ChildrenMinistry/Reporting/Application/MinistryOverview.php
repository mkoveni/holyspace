<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Reporting\Application;

use App\ChildrenMinistry\Incidents\Application\Contract\IncidentPage;
use App\ChildrenMinistry\Safeguarding\Application\Contract\FollowUpView;
use App\ChildrenMinistry\Sessions\Application\Contract\TeamPlanningView;
use App\Communications\Application\Contract\DeliveryPage;
use DateTimeImmutable;

/** The ministry week at a glance. A section the caller may not see is null, never zero. */
final readonly class MinistryOverview
{
    public function __construct(
        public DateTimeImmutable $checkedAt,
        public string $timeZone,
        public ?UpcomingSessions $upcoming,
        public ?TeamPlanningView $teams,
        public string $teamNotice,
        public ?FollowUpView $safeguarding,
        public string $safeguardingNotice,
        public ?IncidentPage $incidents,
        public ?DeliveryPage $communications,
    ) {
    }
}
