<?php

declare(strict_types=1);

namespace App\Tests\ChildrenMinistry\Participation\Application;

use App\ChildrenMinistry\Participation\Domain\ClassAssignment;
use App\ChildrenMinistry\Participation\Domain\Enrolment;
use App\ChildrenMinistry\Participation\Domain\MinistryClass;
use App\ChildrenMinistry\Participation\Domain\Programme;
use App\ChildrenMinistry\Participation\Domain\Repository\ParticipationRepository;
use App\Tests\ChildrenMinistry\Shared\CallLog;
use DateTimeImmutable;

/** In-memory repository that records the order of every read and write, so lock ordering can be asserted. */
final class RecordingParticipationRepository implements ParticipationRepository
{
    /** @var array<string, Enrolment> */
    public array $enrolments = [];
    /** @var array<string, ClassAssignment> */
    public array $assignments = [];
    /** @var array<string, MinistryClass> */
    public array $classes = [];

    public ?Programme $programmeRow = null;

    public function __construct(private readonly CallLog $log)
    {
    }

    public function insertProgramme(Programme $programme): void
    {
        $this->log->entries[] = 'insert:programme';
    }

    public function programmeForUpdate(string $id): ?Programme
    {
        $this->log->entries[] = 'forUpdate:programme';

        return $this->programmeRow;
    }

    public function updateProgramme(Programme $programme): void
    {
        $this->log->entries[] = 'update:programme';
    }

    public function insertClass(MinistryClass $class): void
    {
        $this->log->entries[] = 'insert:class';
    }

    public function findClass(string $id): ?MinistryClass
    {
        $this->log->entries[] = 'find:class';

        return $this->classes[$id] ?? null;
    }

    public function enrolmentForUpdate(string $id): ?Enrolment
    {
        $this->log->entries[] = 'forUpdate:enrolment';

        return $this->enrolments[$id] ?? null;
    }

    public function insertEnrolment(Enrolment $enrolment): void
    {
        $this->log->entries[] = 'insert:enrolment';
        $this->enrolments[$enrolment->id] = $enrolment;
    }

    public function updateEnrolment(Enrolment $enrolment): void
    {
        $this->log->entries[] = 'update:enrolment';
        $this->enrolments[$enrolment->id] = $enrolment;
    }

    public function endAssignments(string $enrolmentId, DateTimeImmutable $at): void
    {
        $this->log->entries[] = 'endAssignments';
    }

    public function classAssignmentForUpdate(string $id): ?ClassAssignment
    {
        $this->log->entries[] = 'forUpdate:assignment';

        return $this->assignments[$id] ?? null;
    }

    public function insertClassAssignment(ClassAssignment $assignment): void
    {
        $this->log->entries[] = 'insert:assignment';
        $this->assignments[$assignment->id] = $assignment;
    }

    public function updateClassAssignment(ClassAssignment $assignment): void
    {
        $this->log->entries[] = 'update:assignment';
        $this->assignments[$assignment->id] = $assignment;
    }
}
