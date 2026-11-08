<?php

declare(strict_types=1);

namespace App\Volunteering\Application\Query\GetVolunteerReadiness;

final readonly class GetVolunteerReadinessQuery
{
    public function __construct(
        public string $volunteerId,
        public string $shiftId,
    ) {
    }
}
