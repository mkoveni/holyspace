<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Sessions\Application\Contract;

use App\ChildrenMinistry\Sessions\Domain\MinistrySession;

final readonly class PlanningSessionView
{
    /**
     * @param list<string>            $issues
     * @param list<PlanningStaffView> $team
     */
    public function __construct(
        public MinistrySession $session,
        public string $programmeId,
        public string $programme,
        public string $ministryClass,
        public string $room,
        public string $timeZone,
        public int $requiredStaff,
        public int $qualifiedStaff,
        public bool $qualifiedLeader,
        public int $plannedCapacity,
        public bool $ready,
        public bool $editable,
        public array $issues,
        public array $team,
    ) {
    }
}
