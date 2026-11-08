<?php

declare(strict_types=1);

namespace App\Communities\Application\Contract;

use App\Events\Application\Contract\CalendarOccurrence;

/** Java `CommunityViews.Meeting`: the meeting, its Events date and its roster counts. */
final readonly class MeetingSummary
{
    public function __construct(public MeetingView $meeting, public CalendarOccurrence $date, public AttendanceReport $counts)
    {
    }
}
