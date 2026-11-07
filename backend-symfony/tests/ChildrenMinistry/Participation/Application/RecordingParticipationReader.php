<?php

declare(strict_types=1);

namespace App\Tests\ChildrenMinistry\Participation\Application;

use App\ChildrenMinistry\Participation\Application\AssignmentView;
use App\ChildrenMinistry\Participation\Application\ClassView;
use App\ChildrenMinistry\Participation\Application\Contract\ParticipationReader;
use App\ChildrenMinistry\Participation\Application\EnrolmentView;
use App\ChildrenMinistry\Participation\Application\ProgrammeView;
use App\Tests\ChildrenMinistry\Shared\CallLog;
use DateTimeImmutable;

/** Reader fake that logs reads, because a handler must learn the child from an unlocked read before locking. */
final class RecordingParticipationReader implements ParticipationReader
{
    /**
     * @param list<string>                  $programmeIds
     * @param array<string, EnrolmentView>  $enrolments
     * @param array<string, AssignmentView> $assignments
     */
    public function __construct(
        private readonly CallLog $log,
        private readonly array $programmeIds = [],
        private readonly array $enrolments = [],
        private readonly array $assignments = [],
    ) {
    }

    public function programme(string $id): ?ProgrammeView
    {
        $this->log->entries[] = 'read:programme';

        return in_array($id, $this->programmeIds, true) ? new ProgrammeView($id, 'P', null, 1, 2, 0, new DateTimeImmutable()) : null;
    }

    public function enrolment(string $id): ?EnrolmentView
    {
        $this->log->entries[] = 'read:enrolment';

        return $this->enrolments[$id] ?? null;
    }

    public function classAssignment(string $id): ?AssignmentView
    {
        $this->log->entries[] = 'read:assignment';

        return $this->assignments[$id] ?? null;
    }

    public function programmes(string $search, int $limit, int $offset): array
    {
        return [];
    }

    public function ministryClass(string $id): ?ClassView
    {
        return null;
    }

    public function classes(string $programmeId): array
    {
        return [];
    }

    public function enrolments(string $childId, int $limit, int $offset): array
    {
        return [];
    }

    public function assignments(string $childId, int $limit, int $offset): array
    {
        return [];
    }

    public function enrolmentAt(string $programmeId, string $childId, \App\Shared\Domain\CalendarDate $on): ?EnrolmentView
    {
        return null;
    }

    public function assignmentAt(string $classId, string $childId, \App\Shared\Domain\CalendarDate $on): ?AssignmentView
    {
        return null;
    }

    public function expectedChildren(string $classId, \App\Shared\Domain\CalendarDate $on, int $limit, int $offset): array
    {
        return [];
    }
}
