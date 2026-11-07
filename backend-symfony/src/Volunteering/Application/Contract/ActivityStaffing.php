<?php

declare(strict_types=1);

namespace App\Volunteering\Application\Contract;

/** Published staffing totals for an activity (for example an event occurrence), counts only. */
interface ActivityStaffing
{
    public function summarize(string $sourceKind, string $sourceId): StaffingSummary;
}
