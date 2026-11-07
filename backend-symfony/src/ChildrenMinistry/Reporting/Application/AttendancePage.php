<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Reporting\Application;

/** What the store reads: visits without names and the totals of the whole filtered result. */
final readonly class AttendancePage
{
    /** @param list<ReportVisit> $visits */
    public function __construct(public array $visits, public ReportSummary $summary)
    {
    }
}
