<?php

declare(strict_types=1);

namespace App\Tests\Volunteering\Application\Roster;

use App\Tests\Volunteering\Application\Teams\LockJournal;
use App\Volunteering\Application\Contract\AssignmentView;
use App\Volunteering\Application\Contract\InvitationRecord;
use App\Volunteering\Application\Contract\RosterDirectory;
use App\Volunteering\Application\Contract\ShiftView;
use App\Volunteering\Application\ShiftViews;
use App\Volunteering\Domain\Assignment;
use App\Volunteering\Domain\Invitation;
use App\Volunteering\Domain\Repository\RosterRepository;
use App\Volunteering\Domain\Shift;
use DateTimeImmutable;
use LogicException;

/** Shifts, assignments and invitations in memory; locks are journaled, writes recorded as "kind:id". */
final class MemoryRoster implements RosterRepository, RosterDirectory
{
    /** @var array<string, Shift> */
    public array $shifts = [];

    /** @var array<string, Assignment> */
    public array $assignments = [];

    /** @var array<string, Invitation> */
    public array $invitations = [];

    /** @var list<string> */
    public array $writes = [];

    public function __construct(private readonly LockJournal $journal)
    {
    }

    public function lockShift(string $id): ?Shift
    {
        $this->journal->entries[] = 'shift:'.$id;

        return $this->shifts[$id] ?? null;
    }

    public function lockAssignment(string $id): ?Assignment
    {
        $this->journal->entries[] = 'assignment:'.$id;

        return $this->assignments[$id] ?? null;
    }

    public function lockInvitation(string $id): ?Invitation
    {
        $this->journal->entries[] = 'invitation:'.$id;

        return $this->invitations[$id] ?? null;
    }

    public function insertShift(Shift $shift): void
    {
        $this->writes[] = 'shift:'.$shift->id;
        $this->shifts[$shift->id] = $shift;
    }

    public function saveShift(Shift $shift): void
    {
        $this->insertShift($shift);
    }

    public function insertAssignment(Assignment $assignment): void
    {
        $this->writes[] = 'assignment:'.$assignment->id;
        $this->assignments[$assignment->id] = $assignment;
    }

    public function saveAssignment(Assignment $assignment): void
    {
        $this->insertAssignment($assignment);
    }

    public function insertInvitation(Invitation $invitation): void
    {
        $this->writes[] = 'invitation:'.$invitation->id;
        $this->invitations[$invitation->id] = $invitation;
    }

    public function saveInvitation(Invitation $invitation): void
    {
        $this->insertInvitation($invitation);
    }

    public function event(string $id, string $assignmentId, string $actorId, string $action, string $reason, DateTimeImmutable $at): void
    {
        $this->writes[] = 'event:'.$action;
    }

    public function assignment(string $id): ?AssignmentView
    {
        $a = $this->assignments[$id] ?? null;

        return null === $a ? null : new AssignmentView($a->id, $a->shiftId, $a->volunteerId, $a->status, $a->requestId, $a->requestHash, $a->version, $a->createdBy, $a->createdAt);
    }

    public function shift(string $id): ?ShiftView
    {
        return isset($this->shifts[$id]) ? ShiftViews::of($this->shifts[$id]) : null;
    }

    public function sourceShifts(string $kind, string $sourceId, int $limit): array
    {
        return [];
    }

    public function assignments(string $shiftId): array
    {
        $views = [];
        foreach ($this->assignments as $assignment) {
            if ($assignment->shiftId === $shiftId) {
                $views[] = $this->assignment($assignment->id) ?? throw new LogicException();
            }
        }

        return $views;
    }

    public function volunteerAssignments(string $volunteerId, DateTimeImmutable $from, DateTimeImmutable $until): array
    {
        return [];
    }

    public function shiftRequest(string $requestId): ?ShiftView
    {
        return null;
    }

    public function shifts(DateTimeImmutable $from, DateTimeImmutable $until, ?string $teamId, int $limit, int $offset): array
    {
        return [];
    }

    public function shiftCount(DateTimeImmutable $from, DateTimeImmutable $until, ?string $teamId): int
    {
        return 0;
    }

    public function assignmentRequest(string $requestId): ?AssignmentView
    {
        return null;
    }

    public function invitation(string $id): ?InvitationRecord
    {
        $i = $this->invitations[$id] ?? null;

        return null === $i ? null : new InvitationRecord($i->id, $i->assignmentId, $i->assignmentVersion, $i->shiftVersion, $i->expiresAt, $i->status, $i->version, $i->respondedAt);
    }

    public function invitations(string $assignmentId): array
    {
        $records = [];
        foreach ($this->invitations as $invitation) {
            if ($invitation->assignmentId === $assignmentId) {
                $records[] = $this->invitation($invitation->id) ?? throw new LogicException();
            }
        }

        return $records;
    }

    public function expirable(DateTimeImmutable $at, int $limit): array
    {
        return [];
    }

    public function myInvitations(string $volunteerId, int $limit, int $offset): array
    {
        return [];
    }

    public function events(string $assignmentId, int $limit, int $offset): array
    {
        return [];
    }

    public function duties(string $volunteerId, DateTimeImmutable $from, DateTimeImmutable $until, int $limit, int $offset): array
    {
        return [];
    }
}
