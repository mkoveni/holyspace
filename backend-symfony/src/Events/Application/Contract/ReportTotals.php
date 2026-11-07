<?php

declare(strict_types=1);

namespace App\Events\Application\Contract;

final readonly class ReportTotals
{
    public function __construct(
        public int $occurrences,
        public int $confirmed,
        public int $waitlisted,
        public int $cancelled,
        public int $attended,
        public int $absent,
    ) {
    }
}
