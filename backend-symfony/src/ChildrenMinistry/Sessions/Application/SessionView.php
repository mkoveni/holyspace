<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Sessions\Application;

use App\ChildrenMinistry\Sessions\Domain\MinistrySession;

final readonly class SessionView
{
    /** @param list<StaffView> $staff */
    public function __construct(
        public MinistrySession $session,
        public string $className,
        public string $roomName,
        public string $timeZone,
        public int $effectiveCapacity,
        public int $occupancy,
        public bool $staffingNeedsReview,
        public array $staff,
    ) {
    }
}
