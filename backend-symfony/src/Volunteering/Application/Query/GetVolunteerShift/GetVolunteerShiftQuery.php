<?php

declare(strict_types=1);

namespace App\Volunteering\Application\Query\GetVolunteerShift;

final readonly class GetVolunteerShiftQuery
{
    public function __construct(
        public string $shiftId,
    ) {
    }
}
