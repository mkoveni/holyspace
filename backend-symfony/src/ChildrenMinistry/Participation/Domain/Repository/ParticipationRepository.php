<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Participation\Domain\Repository;

use App\ChildrenMinistry\Participation\Domain\ClassAssignment;
use App\ChildrenMinistry\Participation\Domain\Enrolment;
use App\ChildrenMinistry\Participation\Domain\MinistryClass;
use App\ChildrenMinistry\Participation\Domain\Programme;
use DateTimeImmutable;

/** Persists programmes, classes, enrolments and class placements. Consent policies live in Safeguarding. */
interface ParticipationRepository
{
    public function insertProgramme(Programme $programme): void;

    public function programmeForUpdate(string $id): ?Programme;

    /** Saves a new revision. Throws a conflict when the stored version is not the previous one. */
    public function updateProgramme(Programme $programme): void;

    public function insertClass(MinistryClass $class): void;

    /** Loads a class (without locking) so a placement can be checked against its age range. */
    public function findClass(string $id): ?MinistryClass;

    /** Loads the enrolment holding a row lock until the surrounding transaction ends. */
    public function enrolmentForUpdate(string $id): ?Enrolment;

    public function insertEnrolment(Enrolment $enrolment): void;

    /** Saves a new revision. Throws a conflict when the stored version is not the previous one. */
    public function updateEnrolment(Enrolment $enrolment): void;

    /** Ends every still-running class placement of the enrolment. */
    public function endAssignments(string $enrolmentId, DateTimeImmutable $at): void;

    public function classAssignmentForUpdate(string $id): ?ClassAssignment;

    public function insertClassAssignment(ClassAssignment $assignment): void;

    public function updateClassAssignment(ClassAssignment $assignment): void;
}
