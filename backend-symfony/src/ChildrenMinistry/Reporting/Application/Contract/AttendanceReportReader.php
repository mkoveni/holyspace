<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Reporting\Application\Contract;

use App\ChildrenMinistry\Reporting\Application\AttendanceCriteria;
use App\ChildrenMinistry\Reporting\Application\AttendancePage;

interface AttendanceReportReader
{
    /** Filters first, then pages: totals cover the whole filtered result. Newest arrival first, or oldest first for one attendance's journey. */
    public function read(AttendanceCriteria $criteria, int $limit, int $offset): AttendancePage;
}
