<?php

declare(strict_types=1);

namespace App\Volunteering\Application\Query\ListVolunteerActivities;

use DateTimeImmutable;

final readonly class ListVolunteerActivitiesQuery
{
    public function __construct(
        public string $kind,
        public ?DateTimeImmutable $from,
        public ?DateTimeImmutable $until,
        public int $limit,
        public int $offset,
    ) {
    }
}
