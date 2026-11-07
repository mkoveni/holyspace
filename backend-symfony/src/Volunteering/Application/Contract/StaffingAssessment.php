<?php

declare(strict_types=1);

namespace App\Volunteering\Application\Contract;

/** Readiness of one volunteer assignment for one exact duty interval. Ready exactly when there are no issues. */
final readonly class StaffingAssessment
{
    /** @param list<string> $issues human-readable reasons the assignment is not ready; empty when ready */
    public function __construct(
        public string $assignmentId,
        public string $personId,
        public string $name,
        public string $responsibility,
        public bool $ready,
        public array $issues,
    ) {
    }
}
