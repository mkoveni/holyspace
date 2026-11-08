<?php

declare(strict_types=1);

namespace App\Volunteering\Application\Query\GetVolunteer;

final readonly class GetVolunteerQuery
{
    public function __construct(
        public string $volunteerId,
    ) {
    }
}
