<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Reporting\Application;

use App\Shared\Domain\CalendarDate;

final readonly class AttendanceReportFilter
{
    public function __construct(public CalendarDate $from, public CalendarDate $until, public ?string $programmeId, public ?string $locationId, public ?string $childId, public string $search, public ?string $outcome)
    {
    }
}
