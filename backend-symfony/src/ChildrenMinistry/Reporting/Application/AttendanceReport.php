<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Reporting\Application;

use DateTimeImmutable;

final readonly class AttendanceReport
{
    /** @param list<ReportVisit> $visits */
    public function __construct(public array $visits, public ReportSummary $summary, public string $timeZone, public DateTimeImmutable $checkedAt)
    {
    }
}
