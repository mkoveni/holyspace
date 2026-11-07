<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Sessions\Application\Contract;

use DateTimeImmutable;

final readonly class PlanningStaffView
{
    /** @param list<string> $issues */
    public function __construct(
        public string $workerId,
        public string $name,
        public string $role,
        public bool $present,
        public bool $qualified,
        public bool $clash,
        public ?DateTimeImmutable $screenedUntil,
        public ?DateTimeImmutable $trainedUntil,
        public array $issues,
    ) {
    }
}
