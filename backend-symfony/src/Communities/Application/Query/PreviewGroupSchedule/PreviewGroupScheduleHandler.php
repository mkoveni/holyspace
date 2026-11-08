<?php

declare(strict_types=1);

namespace App\Communities\Application\Query\PreviewGroupSchedule;

use App\Communities\Application\CommunityAccess;
use App\Communities\Application\MeetingCalendar;
use App\Events\Application\Contract\CalendarPreview;

/** Previews the dates a schedule would create and the hash to confirm them with; Events also requires `events.manage`. */
final readonly class PreviewGroupScheduleHandler
{
    public function __construct(private CommunityAccess $access, private MeetingCalendar $calendar)
    {
    }

    public function handle(PreviewGroupScheduleQuery $query): CalendarPreview
    {
        $this->access->directoryScope();
        $this->access->require($query->communityId);

        return $this->calendar->preview($query->schedule);
    }
}
