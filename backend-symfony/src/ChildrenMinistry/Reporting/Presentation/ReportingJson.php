<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Reporting\Presentation;

use App\ChildrenMinistry\Reporting\Application\AttendanceReport;
use App\ChildrenMinistry\Reporting\Application\MinistryOverview;
use App\ChildrenMinistry\Reporting\Application\OverviewSession;
use App\ChildrenMinistry\Reporting\Application\ReportVisit;
use App\Shared\Presentation\Json;

/** Wire format of attendance reports, matching the Java API's JSON field names. */
final class ReportingJson
{
    private function __construct()
    {
    }

    /** @return array<string, mixed> */
    public static function report(AttendanceReport $report): array
    {
        $summary = $report->summary;

        return [
            'visits' => array_map(static fn (ReportVisit $v): array => [
                'id' => $v->id,
                'attendanceId' => $v->attendanceId,
                'childId' => $v->childId,
                'childName' => $v->childName,
                'sessionId' => $v->sessionId,
                'programmeId' => $v->programmeId,
                'programme' => $v->programme,
                'ministryClass' => $v->ministryClass,
                'room' => $v->room,
                'location' => $v->location,
                'timeZone' => $v->timeZone,
                'arrivedAt' => Json::instant($v->arrivedAt),
                'leftAt' => null === $v->leftAt ? null : Json::instant($v->leftAt),
                'outcome' => $v->outcome,
                'supervisorCollection' => $v->supervisorCollection,
            ], $report->visits),
            'summary' => ['visits' => $summary->visits, 'children' => $summary->children, 'attending' => $summary->attending, 'collected' => $summary->collected, 'transferred' => $summary->transferred, 'supervisorCollections' => $summary->supervisorCollections],
            'timeZone' => $report->timeZone,
            'checkedAt' => Json::instant($report->checkedAt),
        ];
    }

    /** @return array<string, mixed> */
    public static function overview(MinistryOverview $overview): array
    {
        return [
            'checkedAt' => Json::instant($overview->checkedAt),
            'timeZone' => $overview->timeZone,
            'upcoming' => null === $overview->upcoming ? null : [
                'total' => $overview->upcoming->total,
                'sessions' => array_map(static fn (OverviewSession $s): array => ['id' => $s->id, 'programme' => $s->programme, 'ministryClass' => $s->ministryClass, 'room' => $s->room, 'startsAt' => Json::instant($s->startsAt), 'status' => $s->status], $overview->upcoming->sessions),
            ],
            'teams' => null === $overview->teams ? null : OverviewSections::teams($overview->teams),
            'teamNotice' => $overview->teamNotice,
            'safeguarding' => null === $overview->safeguarding ? null : OverviewSections::followUp($overview->safeguarding),
            'safeguardingNotice' => $overview->safeguardingNotice,
            'incidents' => null === $overview->incidents ? null : OverviewSections::incidents($overview->incidents),
            'communications' => null === $overview->communications ? null : OverviewSections::deliveries($overview->communications),
        ];
    }
}
