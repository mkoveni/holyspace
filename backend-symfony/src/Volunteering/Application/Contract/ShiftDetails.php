<?php

declare(strict_types=1);

namespace App\Volunteering\Application\Contract;

/**
 * Java `ServingViews.ShiftView`. Reserved counts every live booking, confirmed only confirmed ones, and ready only
 * confirmed bookings whose volunteer is ready; vacancies are positions not yet reserved.
 */
final readonly class ShiftDetails
{
    /** @param list<Booking> $assignments */
    public function __construct(
        public ShiftView $shift,
        public string $team,
        public string $role,
        public array $assignments,
        public int $reserved,
        public int $confirmed,
        public int $ready,
        public int $vacancies,
    ) {
    }
}
