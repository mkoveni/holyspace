<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Sessions\Presentation;

use App\ChildrenMinistry\Sessions\Application\AttendanceView;
use App\ChildrenMinistry\Sessions\Application\ExpectedChild;
use App\ChildrenMinistry\Sessions\Application\LabelIssue;
use App\ChildrenMinistry\Sessions\Application\PickupCollector;
use App\ChildrenMinistry\Sessions\Application\SessionCloseout;
use App\ChildrenMinistry\Sessions\Application\SessionDayChild;
use App\ChildrenMinistry\Sessions\Application\SessionDayPage;
use App\ChildrenMinistry\Sessions\Application\StationAttendance;
use App\ChildrenMinistry\Sessions\Domain\AttendanceEvent;
use App\ChildrenMinistry\Sessions\Domain\AttendanceSegment;
use App\Shared\Presentation\Json;
use DateTimeImmutable;

/** Wire format of attendance resources, matching the Java API's JSON field names. */
final class AttendanceJson
{
    private function __construct()
    {
    }

    /** @return array<string, mixed> */
    public static function view(AttendanceView $view): array
    {
        return [
            'id' => $view->id,
            'childId' => $view->childId,
            'sessionId' => $view->sessionId,
            'arrivedAt' => Json::instant($view->arrivedAt),
            'checkedOutAt' => self::time($view->checkedOutAt),
            'collectorId' => $view->collectorId,
            'authorizationId' => $view->authorizationId,
            'identityVerified' => $view->identityVerified,
            'exceptionUsed' => $view->exceptionUsed,
            'labelVersion' => $view->labelVersion,
            'labelExpiresAt' => self::time($view->labelExpiresAt),
            'labelIssued' => $view->labelIssued,
            'version' => $view->version,
        ];
    }

    /** @return array<string, mixed> */
    public static function label(LabelIssue $label): array
    {
        return ['attendanceId' => $label->attendanceId, 'labelVersion' => $label->labelVersion, 'pickupCode' => $label->pickupCode, 'expiresAt' => Json::instant($label->expiresAt), 'attendanceVersion' => $label->attendanceVersion];
    }

    /** @return array<string, mixed> */
    public static function event(AttendanceEvent $event): array
    {
        return ['id' => $event->id, 'attendanceId' => $event->attendanceId, 'actorId' => $event->actorId, 'type' => $event->type, 'reason' => $event->reason, 'occurredAt' => Json::instant($event->occurredAt)];
    }

    /** @return array<string, mixed> */
    public static function segment(AttendanceSegment $segment): array
    {
        return ['id' => $segment->id, 'attendanceId' => $segment->attendanceId, 'sessionId' => $segment->sessionId, 'enrolmentId' => $segment->enrolmentId, 'assignmentId' => $segment->assignmentId, 'consentId' => $segment->consentId, 'arrivedAt' => Json::instant($segment->arrivedAt), 'leftAt' => self::time($segment->leftAt)];
    }

    /** @return array<string, mixed> */
    public static function expected(ExpectedChild $child): array
    {
        return ['childId' => $child->childId, 'name' => $child->name, 'enrolmentId' => $child->enrolmentId, 'assignmentId' => $child->assignmentId];
    }

    /** @return array<string, mixed> */
    public static function collector(PickupCollector $collector): array
    {
        return ['authorizationId' => $collector->authorizationId, 'collectorId' => $collector->collectorId, 'name' => $collector->name, 'validUntil' => Json::instant($collector->validUntil)];
    }

    /** @return array<string, mixed> */
    public static function station(StationAttendance $row): array
    {
        return ['attendance' => self::view($row->attendance), 'childName' => $row->childName];
    }

    /** @return array<string, mixed> */
    public static function day(SessionDayPage $page): array
    {
        return [
            'children' => array_map(static fn (SessionDayChild $child): array => [
                'childId' => $child->childId,
                'name' => $child->name,
                'attendance' => null === $child->attendance ? null : self::view($child->attendance),
                'ready' => $child->ready,
                'issues' => $child->issues,
            ], $page->children),
            'total' => $page->total,
            'checkedAt' => Json::instant($page->checkedAt),
        ];
    }

    /** @return array<string, mixed> */
    public static function closeout(SessionCloseout $closeout): array
    {
        return [
            'visits' => $closeout->visits,
            'children' => $closeout->children,
            'awaitingCollection' => $closeout->awaitingCollection,
            'collected' => $closeout->collected,
            'transferredOut' => $closeout->transferredOut,
            'labelsNeedingAttention' => $closeout->labelsNeedingAttention,
            'failedHandoverAttempts' => $closeout->failedHandoverAttempts,
            'supervisorCollections' => $closeout->supervisorCollections,
            'checkedAt' => Json::instant($closeout->checkedAt),
            'incidents' => null === $closeout->incidents ? null : ['unresolved' => $closeout->incidents->unresolved, 'overdue' => $closeout->incidents->overdue, 'unassigned' => $closeout->incidents->unassigned],
        ];
    }

    private static function time(?DateTimeImmutable $time): ?string
    {
        return null === $time ? null : Json::instant($time);
    }
}
