<?php

declare(strict_types=1);

namespace App\Volunteering\Application\Query\ListVolunteerShifts;

use DateTimeImmutable;

final readonly class ListVolunteerShiftsQuery
{
    public function __construct(
        public ?DateTimeImmutable $from,
        public ?DateTimeImmutable $until,
        public ?string $teamId,
        public int $limit,
        public int $offset,
    ) {
    }
}
