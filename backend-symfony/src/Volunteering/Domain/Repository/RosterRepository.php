<?php

declare(strict_types=1);

namespace App\Volunteering\Domain\Repository;

use App\Volunteering\Domain\Assignment;
use App\Volunteering\Domain\Invitation;
use App\Volunteering\Domain\Shift;
use DateTimeImmutable;

/** Shifts, assignments, invitations and the assignment history. Saves check the previous version. */
interface RosterRepository
{
    public function lockShift(string $id): ?Shift;

    public function lockAssignment(string $id): ?Assignment;

    public function lockInvitation(string $id): ?Invitation;

    /** A reused request id (a parallel retry) is a clash. */
    public function insertShift(Shift $shift): void;

    public function saveShift(Shift $shift): void;

    /** A second live assignment of the volunteer to the shift, or a reused request id, is a clash. */
    public function insertAssignment(Assignment $assignment): void;

    public function saveAssignment(Assignment $assignment): void;

    /** A second pending invitation for the assignment is a clash. */
    public function insertInvitation(Invitation $invitation): void;

    public function saveInvitation(Invitation $invitation): void;

    public function event(string $id, string $assignmentId, string $actorId, string $action, string $reason, DateTimeImmutable $at): void;
}
