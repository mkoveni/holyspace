<?php

declare(strict_types=1);

namespace App\Giving\Application\Contract;

final readonly class PledgeDetail
{
    /**
     * @param list<PledgeAssignmentEntry> $assignments
     * @param list<HistoryEntry>          $history
     */
    public function __construct(
        public PledgeSummary $summary,
        public array $assignments,
        public array $history,
    ) {
    }
}
