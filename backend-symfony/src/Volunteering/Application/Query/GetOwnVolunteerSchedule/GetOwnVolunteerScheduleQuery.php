<?php

declare(strict_types=1);

namespace App\Volunteering\Application\Query\GetOwnVolunteerSchedule;

final readonly class GetOwnVolunteerScheduleQuery
{
    public function __construct(
        public string $actorId,
        public int $limit,
        public int $offset,
    ) {
    }
}
