<?php

declare(strict_types=1);

namespace App\Volunteering\Application\Query\PreviewVolunteerSchedule;

final readonly class PreviewVolunteerScheduleQuery
{
    public function __construct(
        public ?string $requestId,
        public ?string $teamId,
        public ?string $roleId,
        /** @var list<string|null>|null ISO dates */
        public ?array $dates,
        public ?string $startsAt,
        public ?string $endsAt,
        public ?string $timeZone,
        public int $positions,
    ) {
    }
}
