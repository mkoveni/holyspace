<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Sessions\Presentation;

use App\ChildrenMinistry\Sessions\Application\Contract\PlanningSessionView;
use App\ChildrenMinistry\Sessions\Application\Contract\PlanningStaffView;
use App\ChildrenMinistry\Sessions\Application\Contract\TeamPlanningView;
use App\ChildrenMinistry\Sessions\Application\PlanningWorkerList;
use App\ChildrenMinistry\Sessions\Application\PlanningWorkerOption;
use App\ChildrenMinistry\Sessions\Application\TeamPlanPreview;
use App\Shared\Presentation\Json;

/** Wire format of team planning, matching the Java API's JSON field names. No evidence references or account ids. */
final class TeamPlanningJson
{
    private function __construct()
    {
    }

    /** @return array<string, mixed> */
    public static function planning(TeamPlanningView $view): array
    {
        $summary = $view->summary;

        return [
            'sessions' => array_map(self::session(...), $view->sessions),
            'total' => $view->total,
            'summary' => ['sessions' => $summary->sessions, 'ready' => $summary->ready, 'attention' => $summary->attention, 'missingLeaders' => $summary->missingLeaders, 'shortCapacity' => $summary->shortCapacity, 'clashes' => $summary->clashes],
            'timeZone' => $view->timeZone,
            'checkedAt' => Json::instant($view->checkedAt),
        ];
    }

    /** @return array<string, mixed> */
    public static function preview(TeamPlanPreview $preview): array
    {
        return ['sessions' => array_map(self::session(...), $preview->sessions), 'blockers' => $preview->blockers, 'checkedAt' => Json::instant($preview->checkedAt)];
    }

    /** @return array<string, mixed> */
    public static function workers(PlanningWorkerList $list): array
    {
        return [
            'workers' => array_map(static fn (PlanningWorkerOption $worker): array => [
                'id' => $worker->id,
                'name' => $worker->name,
                'screenedUntil' => Json::instant($worker->screenedUntil),
                'trainedUntil' => Json::instant($worker->trainedUntil),
                'revoked' => $worker->revoked,
            ], $list->workers),
            'total' => $list->total,
        ];
    }

    /** @return array<string, mixed> */
    private static function session(PlanningSessionView $row): array
    {
        $session = $row->session;

        return [
            'session' => [
                'id' => $session->id,
                'classId' => $session->classId,
                'roomId' => $session->roomId,
                'startsAt' => Json::instant($session->startsAt),
                'endsAt' => Json::instant($session->endsAt),
                'seatLimit' => $session->seatLimit,
                'status' => $session->status->value,
                'version' => $session->version,
            ],
            'programmeId' => $row->programmeId,
            'programme' => $row->programme,
            'ministryClass' => $row->ministryClass,
            'room' => $row->room,
            'timeZone' => $row->timeZone,
            'requiredStaff' => $row->requiredStaff,
            'qualifiedStaff' => $row->qualifiedStaff,
            'qualifiedLeader' => $row->qualifiedLeader,
            'plannedCapacity' => $row->plannedCapacity,
            'ready' => $row->ready,
            'editable' => $row->editable,
            'issues' => $row->issues,
            'team' => array_map(static fn (PlanningStaffView $member): array => [
                'workerId' => $member->workerId,
                'name' => $member->name,
                'role' => $member->role,
                'present' => $member->present,
                'qualified' => $member->qualified,
                'clash' => $member->clash,
                'screenedUntil' => null === $member->screenedUntil ? null : Json::instant($member->screenedUntil),
                'trainedUntil' => null === $member->trainedUntil ? null : Json::instant($member->trainedUntil),
                'issues' => $member->issues,
            ], $row->team),
        ];
    }
}
