<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Sessions\Application;

use App\ChildrenMinistry\Sessions\Domain\MinistrySession;

/** A session with the programme, class, room and venue facts planning needs. */
final readonly class PlanningSession
{
    public function __construct(
        public MinistrySession $value,
        public string $programmeId,
        public string $programme,
        public string $ministryClass,
        public string $room,
        public string $locationId,
        public string $timeZone,
        public int $roomCapacity,
        public int $minimumStaff,
        public int $childrenPerStaff,
    ) {
    }
}
