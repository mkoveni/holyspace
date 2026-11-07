<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Incidents\Presentation;

use App\ChildrenMinistry\Incidents\Application\Contract\IncidentSummary;
use App\ChildrenMinistry\Incidents\Application\IncidentAccessView;
use App\ChildrenMinistry\Incidents\Application\IncidentAttendanceRow;
use App\ChildrenMinistry\Incidents\Application\IncidentAttendanceView;
use App\ChildrenMinistry\Incidents\Application\IncidentGuardian;
use App\ChildrenMinistry\Incidents\Application\IncidentListView;
use App\ChildrenMinistry\Incidents\Application\IncidentRow;
use App\ChildrenMinistry\Incidents\Domain\IncidentEvent;
use App\IdentityAccess\Application\Contract\ResponsibleAccount;
use App\Shared\Presentation\Json;

/** Wire format of incidents, matching the Java API's JSON field names. */
final class IncidentJson
{
    private function __construct()
    {
    }

    /** @return array<string, mixed> */
    public static function list(IncidentListView $view): array
    {
        return [
            'incidents' => array_map(static fn (IncidentRow $row): array => ['incident' => self::incident($row->incident), 'childName' => $row->childName], $view->incidents),
            'total' => $view->total,
            'overdue' => $view->overdue,
            'unassigned' => $view->unassigned,
        ];
    }

    /** @return array<string, mixed> restricted: private details and history */
    public static function access(IncidentAccessView $view): array
    {
        return [
            'incident' => self::incident($view->incident),
            'details' => ['description' => $view->details->description, 'immediateAction' => $view->details->immediateAction],
            'events' => array_map(static fn (IncidentEvent $event): array => [
                'id' => $event->id,
                'incidentId' => $event->incidentId,
                'revision' => $event->revision,
                'type' => $event->type->value,
                'actorId' => $event->actorId,
                'occurredAt' => Json::instant($event->occurredAt),
                'note' => $event->note,
                'guardianId' => $event->guardianId,
            ], $view->events),
            'totalEvents' => $view->totalEvents,
        ];
    }

    /** @return array<string, mixed> */
    public static function attendance(IncidentAttendanceView $view): array
    {
        return [
            'visits' => array_map(static fn (IncidentAttendanceRow $row): array => [
                'visit' => [
                    'segmentId' => $row->visit->segmentId,
                    'childId' => $row->visit->childId,
                    'arrivedAt' => Json::instant($row->visit->arrivedAt),
                    'leftAt' => null === $row->visit->leftAt ? null : Json::instant($row->visit->leftAt),
                ],
                'childName' => $row->childName,
            ], $view->visits),
            'total' => $view->total,
        ];
    }

    /** @return array<string, string> */
    public static function guardian(IncidentGuardian $guardian): array
    {
        return ['authorityId' => $guardian->authorityId, 'guardianId' => $guardian->guardianId, 'name' => $guardian->name];
    }

    /** @return array<string, string> */
    public static function owner(ResponsibleAccount $account): array
    {
        return ['id' => $account->id, 'name' => $account->name];
    }

    /** @return array<string, mixed> */
    private static function incident(IncidentSummary $incident): array
    {
        return [
            'id' => $incident->id,
            'childId' => $incident->childId,
            'sessionId' => $incident->sessionId,
            'segmentId' => $incident->segmentId,
            'category' => $incident->category->value,
            'severity' => $incident->severity->value,
            'occurredAt' => Json::instant($incident->occurredAt),
            'reportedBy' => $incident->reportedBy,
            'reportedAt' => Json::instant($incident->reportedAt),
            'status' => $incident->status->value,
            'ownerId' => $incident->ownerId,
            'reviewOn' => $incident->reviewOn->iso(),
            'retainUntil' => $incident->retainUntil->iso(),
            'version' => $incident->version,
        ];
    }
}
