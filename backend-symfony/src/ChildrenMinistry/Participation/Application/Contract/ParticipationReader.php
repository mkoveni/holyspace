<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Participation\Application\Contract;

use App\ChildrenMinistry\Participation\Application\AssignmentView;
use App\ChildrenMinistry\Participation\Application\ClassView;
use App\ChildrenMinistry\Participation\Application\EnrolmentView;
use App\ChildrenMinistry\Participation\Application\ProgrammeView;
use App\Shared\Domain\CalendarDate;

/** Read side of programmes and classes, published to the other Children Ministry modules. */
interface ParticipationReader
{
    public function programme(string $id): ?ProgrammeView;

    /** @return list<ProgrammeView> ordered by name */
    public function programmes(string $search, int $limit, int $offset): array;

    public function ministryClass(string $id): ?ClassView;

    public function enrolment(string $id): ?EnrolmentView;

    public function classAssignment(string $id): ?AssignmentView;

    /** @return list<EnrolmentView> the child's enrolments, newest first */
    public function enrolments(string $childId, int $limit, int $offset): array;

    /** @return list<AssignmentView> the child's class placements, most recent start first */
    public function assignments(string $childId, int $limit, int $offset): array;

    /** @return list<ClassView> the programme's classes ordered by name */
    public function classes(string $programmeId): array;

    /** The child's unended enrolment in the programme that covers the day, if any. */
    public function enrolmentAt(string $programmeId, string $childId, CalendarDate $on): ?EnrolmentView;

    /** The child's unended placement in the class that covers the day, if any. */
    public function assignmentAt(string $classId, string $childId, CalendarDate $on): ?AssignmentView;

    /**
     * Placements in the class on the day whose enrolment is also current, ordered by child id.
     *
     * @return list<AssignmentView>
     */
    public function expectedChildren(string $classId, CalendarDate $on, int $limit, int $offset): array;
}
