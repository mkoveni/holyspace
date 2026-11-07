<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Reporting\Application\Contract;

use App\ChildrenMinistry\Reporting\Application\UpcomingSessions;
use DateTimeImmutable;

interface MinistryOverviewReader
{
    /** Sessions starting in (from, until) that are not closed or cancelled. */
    public function upcoming(DateTimeImmutable $from, DateTimeImmutable $until): UpcomingSessions;
}
