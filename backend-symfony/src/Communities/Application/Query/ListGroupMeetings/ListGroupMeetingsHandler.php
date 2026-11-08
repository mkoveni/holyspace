<?php

declare(strict_types=1);

namespace App\Communities\Application\Query\ListGroupMeetings;

use App\Communities\Application\CommunityAccess;
use App\Communities\Application\Contract\MeetingPage;
use App\Communities\Application\Contract\MeetingReader;
use App\Communities\Application\Contract\MeetingSummary;
use App\Communities\Application\Contract\MeetingView;
use App\Communities\Application\MeetingCalendar;
use App\Communities\Domain\CommunityRules;

/** A community's meetings, newest first, each with its Events date and roster counts. */
final readonly class ListGroupMeetingsHandler
{
    public function __construct(private CommunityAccess $access, private MeetingReader $reader, private MeetingCalendar $calendar)
    {
    }

    public function handle(ListGroupMeetingsQuery $query): MeetingPage
    {
        $this->access->directoryScope();
        $this->access->require($query->communityId);
        CommunityRules::page($query->limit, $query->offset);
        $page = $this->reader->meetings($query->communityId, $query->limit, $query->offset);
        $counts = $this->reader->reports(array_map(static fn (MeetingView $m): string => $m->id, $page));
        $dates = $this->calendar->dates(array_map(static fn (MeetingView $m): string => $m->occurrenceId, $page));

        return new MeetingPage(
            array_map(static fn (MeetingView $m): MeetingSummary => new MeetingSummary($m, $dates[$m->occurrenceId], $counts[$m->id]), $page),
            $this->reader->meetingCount($query->communityId),
        );
    }
}
