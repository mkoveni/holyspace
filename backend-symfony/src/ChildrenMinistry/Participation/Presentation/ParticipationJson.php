<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Participation\Presentation;

use App\ChildrenMinistry\Participation\Application\AssignmentView;
use App\ChildrenMinistry\Participation\Application\ClassView;
use App\ChildrenMinistry\Participation\Application\EnrolmentView;
use App\ChildrenMinistry\Participation\Application\LocationView;
use App\ChildrenMinistry\Participation\Application\Placement;
use App\ChildrenMinistry\Participation\Application\ProgrammeChild;
use App\ChildrenMinistry\Participation\Application\ProgrammeChildrenPage;
use App\ChildrenMinistry\Participation\Application\ProgrammeView;
use App\ChildrenMinistry\Participation\Application\RoomView;
use App\Shared\Presentation\Json;

/** Wire format of participation resources, matching the Java API's JSON field names. */
final class ParticipationJson
{
    private function __construct()
    {
    }

    /** @return array<string, mixed> */
    public static function programme(ProgrammeView $programme): array
    {
        return [
            'id' => $programme->id,
            'name' => $programme->name,
            'currentPolicyId' => $programme->currentPolicyId,
            'minStaff' => $programme->minStaff,
            'childrenPerStaff' => $programme->childrenPerStaff,
            'version' => $programme->version,
            'createdAt' => Json::instant($programme->createdAt),
        ];
    }

    /** @return array<string, mixed> */
    public static function ministryClass(ClassView $class): array
    {
        return ['id' => $class->id, 'programmeId' => $class->programmeId, 'name' => $class->name, 'minAgeMonths' => $class->minAgeMonths, 'maxAgeMonths' => $class->maxAgeMonths];
    }

    /** @return array<string, mixed> */
    public static function enrolment(EnrolmentView $enrolment): array
    {
        return [
            'id' => $enrolment->id,
            'programmeId' => $enrolment->programmeId,
            'childId' => $enrolment->childId,
            'validFrom' => $enrolment->validFrom->iso(),
            'validUntil' => $enrolment->validUntil->iso(),
            'endedAt' => null === $enrolment->endedAt ? null : Json::instant($enrolment->endedAt),
            'version' => $enrolment->version,
            'createdAt' => Json::instant($enrolment->createdAt),
        ];
    }

    /** @return array<string, mixed> */
    public static function assignment(AssignmentView $assignment): array
    {
        return [
            'id' => $assignment->id,
            'programmeId' => $assignment->programmeId,
            'classId' => $assignment->classId,
            'childId' => $assignment->childId,
            'enrolmentId' => $assignment->enrolmentId,
            'validFrom' => $assignment->validFrom->iso(),
            'validUntil' => $assignment->validUntil->iso(),
            'endedAt' => null === $assignment->endedAt ? null : Json::instant($assignment->endedAt),
            'version' => $assignment->version,
        ];
    }

    /** @return array<string, mixed> */
    public static function roster(ProgrammeChildrenPage $page): array
    {
        return [
            'children' => array_map(static fn (ProgrammeChild $child): array => [
                'enrolmentId' => $child->enrolmentId,
                'childId' => $child->childId,
                'name' => $child->name,
                'validFrom' => $child->validFrom->iso(),
                'validUntil' => $child->validUntil->iso(),
                'status' => $child->status,
                'classes' => array_map(static fn (Placement $placement): array => ['id' => $placement->id, 'name' => $placement->name], $child->classes),
                'readiness' => null === $child->readiness ? null : ['ready' => $child->readiness->ready, 'completed' => $child->readiness->completed, 'total' => $child->readiness->total],
            ], $page->children),
            'total' => $page->total,
        ];
    }

    /** @return array<string, mixed> */
    public static function location(LocationView $location): array
    {
        return ['id' => $location->id, 'name' => $location->name, 'timeZone' => $location->timeZone];
    }

    /** @return array<string, mixed> */
    public static function room(RoomView $room): array
    {
        return ['id' => $room->id, 'locationId' => $room->locationId, 'name' => $room->name, 'capacity' => $room->capacity];
    }
}
