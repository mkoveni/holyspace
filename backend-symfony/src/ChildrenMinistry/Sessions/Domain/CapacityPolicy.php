<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Sessions\Domain;

/** How many children a session may hold given the staff actually present and eligible. */
final class CapacityPolicy
{
    private function __construct()
    {
    }

    public static function capacity(int $seats, int $roomCapacity, int $eligiblePresent, bool $leaderPresent, int $minimumStaff, int $childrenPerStaff): int
    {
        if ($eligiblePresent < $minimumStaff || !$leaderPresent) {
            return 0;
        }

        return min($seats, $roomCapacity, $eligiblePresent * $childrenPerStaff);
    }
}
