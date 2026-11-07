<?php

declare(strict_types=1);

namespace App\Volunteering\Application\Contract;

/** Operational totals for one source activity; no volunteer identities cross the boundary. */
final readonly class StaffingSummary
{
    public function __construct(
        public int $shifts,
        public int $positions,
        public int $confirmed,
        public int $ready,
        public int $proposed,
        public int $gaps,
        public bool $complete,
    ) {
    }
}
