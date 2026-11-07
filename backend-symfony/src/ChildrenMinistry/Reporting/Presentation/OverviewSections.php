<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Reporting\Presentation;

use App\ChildrenMinistry\Incidents\Application\Contract\IncidentPage;
use App\ChildrenMinistry\Incidents\Application\Contract\IncidentSummary;
use App\ChildrenMinistry\Safeguarding\Application\Contract\FollowUpIssue;
use App\ChildrenMinistry\Safeguarding\Application\Contract\FollowUpView;
use App\ChildrenMinistry\Sessions\Application\Contract\PlanningSessionView;
use App\ChildrenMinistry\Sessions\Application\Contract\PlanningStaffView;
use App\ChildrenMinistry\Sessions\Application\Contract\TeamPlanningView;
use App\Communications\Application\Contract\DeliveryPage;
use App\Communications\Application\Contract\DeliverySummary;
use App\Shared\Presentation\Json;

/**
 * The overview embeds a slice of other areas. Each is written out here from its published contract (a module may not
 * use another's presentation classes), with exactly the field names of that area's own endpoint. Metadata only.
 */
final class OverviewSections
{
    private function __construct()
    {
    }

    /** @return array<string, mixed> */
    public static function teams(TeamPlanningView $view): array
    {
        $summary = $view->summary;

        return [
            'sessions' => array_map(static fn (PlanningSessionView $row): array => [
                'session' => [
                    'id' => $row->session->id,
                    'classId' => $row->session->classId,
                    'roomId' => $row->session->roomId,
                    'startsAt' => Json::instant($row->session->startsAt),
                    'endsAt' => Json::instant($row->session->endsAt),
                    'seatLimit' => $row->session->seatLimit,
                    'status' => $row->session->status->value,
                    'version' => $row->session->version,
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
            ], $view->sessions),
            'total' => $view->total,
            'summary' => ['sessions' => $summary->sessions, 'ready' => $summary->ready, 'attention' => $summary->attention, 'missingLeaders' => $summary->missingLeaders, 'shortCapacity' => $summary->shortCapacity, 'clashes' => $summary->clashes],
            'timeZone' => $view->timeZone,
            'checkedAt' => Json::instant($view->checkedAt),
        ];
    }

    /** @return array<string, mixed> */
    public static function followUp(FollowUpView $view): array
    {
        return [
            'issues' => array_map(static fn (FollowUpIssue $i): array => [
                'id' => $i->id,
                'kind' => $i->kind,
                'state' => $i->state,
                'childId' => $i->childId,
                'childName' => $i->childName,
                'programmeId' => $i->programmeId,
                'programme' => $i->programme,
                'expiresAt' => null === $i->expiresAt ? null : Json::instant($i->expiresAt),
                'reviewOn' => $i->reviewOn?->iso(),
                'action' => $i->action,
                'message' => $i->message,
            ], $view->issues),
            'total' => $view->total,
            'summary' => ['children' => $view->summary->children, 'missing' => $view->summary->missing, 'expired' => $view->summary->expired, 'expiring' => $view->summary->expiring, 'careDue' => $view->summary->careDue],
            'careIncluded' => $view->careIncluded,
            'through' => $view->through->iso(),
            'timeZone' => $view->timeZone,
            'checkedAt' => Json::instant($view->checkedAt),
        ];
    }

    /** @return array<string, mixed> */
    public static function incidents(IncidentPage $page): array
    {
        return [
            'incidents' => array_map(static fn (IncidentSummary $i): array => [
                'id' => $i->id,
                'childId' => $i->childId,
                'sessionId' => $i->sessionId,
                'segmentId' => $i->segmentId,
                'category' => $i->category->value,
                'severity' => $i->severity->value,
                'occurredAt' => Json::instant($i->occurredAt),
                'reportedBy' => $i->reportedBy,
                'reportedAt' => Json::instant($i->reportedAt),
                'status' => $i->status->value,
                'ownerId' => $i->ownerId,
                'reviewOn' => $i->reviewOn->iso(),
                'retainUntil' => $i->retainUntil->iso(),
                'version' => $i->version,
            ], $page->incidents),
            'total' => $page->total,
            'overdue' => $page->overdue,
            'unassigned' => $page->unassigned,
        ];
    }

    /** @return array<string, mixed> */
    public static function deliveries(DeliveryPage $page): array
    {
        return [
            'deliveries' => array_map(static fn (DeliverySummary $s): array => ['id' => $s->id, 'communicationId' => $s->communicationId, 'status' => $s->status, 'scheduledAt' => Json::instant($s->scheduledAt), 'attempts' => $s->attempts, 'errorCode' => $s->errorCode, 'captured' => $s->captured, 'version' => $s->version], $page->deliveries),
            'total' => $page->total,
            'failed' => $page->failed,
            'waiting' => $page->waiting,
        ];
    }
}
