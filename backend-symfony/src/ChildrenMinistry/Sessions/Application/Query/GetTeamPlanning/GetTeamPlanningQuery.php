<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Sessions\Application\Query\GetTeamPlanning;

use App\Shared\Domain\CalendarDate;

final readonly class GetTeamPlanningQuery
{
    public function __construct(
        public CalendarDate $from,
        public CalendarDate $until,
        public ?string $programmeId,
        public ?string $locationId,
        public string $search,
        public string $state,
        public int $limit,
        public int $offset,
    ) {
    }
}
