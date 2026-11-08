<?php

declare(strict_types=1);

namespace App\Volunteering\Application;

use App\Shared\Application\IdGenerator;
use App\Volunteering\Application\Contract\AssignmentView;
use App\Volunteering\Application\Contract\DutyNotices;
use App\Volunteering\Application\Contract\RosterDirectory;
use App\Volunteering\Application\Contract\ServingActivity;
use App\Volunteering\Application\Contract\ServingAudit;
use App\Volunteering\Application\Contract\ShiftDraft;
use App\Volunteering\Application\Contract\TeamReader;
use App\Volunteering\Application\Contract\VolunteerReader;
use App\Volunteering\Domain\Assignment;
use App\Volunteering\Domain\Invitation;
use App\Volunteering\Domain\Repository\RosterRepository;
use App\Volunteering\Domain\Repository\TeamRepository;
use App\Volunteering\Domain\ServingRules;
use App\Volunteering\Domain\Shift;
use App\Volunteering\Domain\VolunteerException;
use DateTimeImmutable;
use Psr\Clock\ClockInterface;

/**
 * Java `RosterPlanning`: shifts, assignments and invitations. Runs inside the command transaction.
 *
 * Lock order (as Java): people sorted by person id, then the linked activities (sorted by kind then id, each context
 * locking its own), then the volunteer rows ({@see VolunteerLocks}); then the team row(s), sorted by id, for shift
 * creation, or the shift row, then the assignment row, then its invitation rows for every booking change.
 */
final readonly class RosterPlanning
{
    private const int MAX_BULK = 25;

    public function __construct(
        private RosterRepository $repository,
        private RosterDirectory $roster,
        private TeamReader $teams,
        private TeamRepository $teamRepository,
        private VolunteerLocks $locks,
        private VolunteerReader $volunteers,
        private ActivityRegistry $activities,
        private ServingAudit $audit,
        private DutyNotices $notices,
        private IdGenerator $ids,
        private ClockInterface $clock,
    ) {
    }

    public function create(string $actorId, ?ShiftDraft $draft): string
    {
        if (null !== $draft) {
            self::validateSource($draft);
            if (null !== $draft->sourceId) {
                $this->locks->lockAll([], [new ServingActivity((string) $draft->sourceKind, $draft->sourceId)]);
            }
        }

        return $this->createLocked($actorId, $draft);
    }

    /**
     * All drafts or none: one failure rolls back the whole command.
     *
     * @param list<ShiftDraft|null>|null $drafts
     *
     * @return list<string> the shift ids, in draft order
     */
    public function bulk(string $actorId, ?array $drafts): array
    {
        if (null === $drafts || [] === $drafts || \count($drafts) > self::MAX_BULK || \in_array(null, $drafts, true)) {
            throw VolunteerException::invalid('Create between 1 and 25 shifts together.');
        }
        /** @var list<ShiftDraft> $drafts */
        $linked = [];
        foreach ($drafts as $draft) {
            self::validateSource($draft);
            if (null !== $draft->sourceId) {
                $linked[] = new ServingActivity((string) $draft->sourceKind, $draft->sourceId);
            }
        }
        $this->locks->lockAll([], $linked);
        $teamIds = array_values(array_unique(array_map(static fn (ShiftDraft $draft): string => (string) $draft->teamId, $drafts)));
        sort($teamIds, \SORT_STRING);
        foreach ($teamIds as $teamId) {
            $this->teamRepository->lock($teamId) ?? throw VolunteerException::missing('Team');
        }

        return array_map(fn (ShiftDraft $draft): string => $this->createLocked($actorId, $draft), $drafts);
    }

    public function update(string $actorId, string $id, int $expectedVersion, ?DateTimeImmutable $from, ?DateTimeImmutable $until, int $positions, ?string $status): void
    {
        $before = $this->roster->shift($id) ?? throw VolunteerException::missing('Shift');
        $volunteerIds = array_values(array_unique(array_map(static fn (AssignmentView $a): string => $a->volunteerId, $this->live($id))));
        $this->locks->lockAll($volunteerIds, ShiftViews::activity($before));
        $shift = $this->repository->lockShift($id) ?? throw VolunteerException::missing('Shift');
        ServingRules::version($shift->version, $expectedVersion);
        $assignments = $this->live($id);
        $cancelling = 'CANCELLED' === $status;
        if ($positions < \count($assignments) && !$cancelling) {
            throw VolunteerException::conflict('Positions cannot be reduced below reserved assignments.');
        }
        $changed = $shift->change($from, $until, $positions, $status);
        $revised = $changed->withSourceRevision($cancelling ? $shift->sourceRevision : $this->activities->revision(ShiftViews::of($changed)));
        if (!$cancelling) {
            $this->activities->require(ShiftViews::of($revised));
            foreach ($assignments as $assignment) {
                foreach ($this->roster->volunteerAssignments($assignment->volunteerId, $revised->startsAt, $revised->endsAt) as $other) {
                    if ($other->id !== $assignment->id) {
                        throw VolunteerException::conflict("The revised shift overlaps a volunteer's other assignment.");
                    }
                }
            }
        }
        $this->repository->saveShift($revised);
        $now = $this->clock->now();
        $moved = $shift->startsAt != $revised->startsAt || $shift->endsAt != $revised->endsAt || $shift->sourceRevision !== $revised->sourceRevision;
        foreach ($assignments as $view) {
            $assignment = $this->repository->lockAssignment($view->id) ?? throw VolunteerException::missing('Assignment');
            $this->withdraw($view->id, $now);
            if ($cancelling) {
                $this->repository->saveAssignment($assignment->change('CANCELLED'));
            } elseif ('CONFIRMED' === $assignment->status && $moved) {
                $this->repository->saveAssignment($assignment->change('PROPOSED'));
            }
            $this->notices->changed($this->account($view->volunteerId), 'shift:'.$id.':'.$revised->version);
            $this->event($view->id, $actorId, 'SHIFT_CHANGED', 'Review the revised shift details.', $now);
        }
        $this->audit->record($actorId, 'shift.changed', $id, $now);
    }

    public function assign(string $actorId, ?string $requestId, string $shiftId, int $expectedVersion, string $volunteerId, ?string $status): string
    {
        if ('PROPOSED' !== $status && 'CONFIRMED' !== $status) {
            throw VolunteerException::invalid('Choose a proposed or confirmed commitment.');
        }
        $requestId = ServingRules::id($requestId);
        $before = $this->roster->shift($shiftId) ?? throw VolunteerException::missing('Shift');
        $volunteer = $this->locks->lockAll([$volunteerId], ShiftViews::activity($before))[0];
        if (!$volunteer->isActive()) {
            throw VolunteerException::conflict('Only active volunteers can be scheduled.');
        }
        $shift = $this->repository->lockShift($shiftId) ?? throw VolunteerException::missing('Shift');
        $hash = Fingerprint::of($shiftId, $volunteerId, $status);
        $previous = $this->roster->assignmentRequest($requestId);
        if (null !== $previous) {
            if ($previous->requestHash !== $hash) {
                throw VolunteerException::conflict('This request identifier was already used.');
            }

            return $previous->id;
        }
        ServingRules::version($shift->version, $expectedVersion);
        $now = $this->clock->now();
        if (!$shift->isOpen() || !($shift->endsAt > $now)) {
            throw VolunteerException::conflict('Choose an upcoming open shift.');
        }
        $this->activities->require(ShiftViews::of($shift));
        if (true !== $this->teams->team($shift->teamId)?->active) {
            throw VolunteerException::conflict('The team is inactive.');
        }
        if (true !== $this->teams->role($shift->roleId)?->active) {
            throw VolunteerException::conflict('The role is inactive.');
        }
        if (\count($this->live($shiftId)) >= $shift->positions) {
            throw VolunteerException::conflict('All positions are reserved.');
        }
        if ([] !== $this->roster->volunteerAssignments($volunteerId, $shift->startsAt, $shift->endsAt)) {
            throw VolunteerException::conflict('The volunteer has an overlapping assignment.');
        }
        $assignment = Assignment::book($this->ids->uuid(), $requestId, $hash, $shiftId, $volunteerId, $status, $actorId, $now);
        $this->repository->insertAssignment($assignment);
        $this->repository->saveShift($shift->touch());
        $this->event($assignment->id, $actorId, 'ASSIGNED', $status, $now);
        $this->notices->assigned($volunteer->accountId, $assignment->id);
        $this->audit->record($actorId, 'assignment.created', $assignment->id, $now);

        return $assignment->id;
    }

    public function change(string $actorId, string $id, int $expectedVersion, ?string $status, ?string $reason): void
    {
        $note = ServingRules::text($reason, 500);
        $locked = $this->lock($id);
        $assignment = $locked->assignment;
        ServingRules::version($assignment->version, $expectedVersion);
        if ('CANCELLED' !== $status) {
            if (!$locked->shift->isOpen()) {
                throw VolunteerException::conflict('The shift is cancelled.');
            }
            $this->activities->require(ShiftViews::of($locked->shift));
        }
        $now = $this->clock->now();
        $this->withdraw($id, $now);
        $this->repository->saveAssignment($assignment->change($status));
        $this->repository->saveShift($locked->shift->touch());
        $this->event($id, $actorId, 'STATUS_CHANGED', $note, $now);
        $this->notices->changed($locked->volunteer->accountId, 'assignment:'.$id.':'.($assignment->version + 1));
        $this->audit->record($actorId, 'assignment.changed', $id, $now);
    }

    public function invite(string $actorId, string $id, int $expectedVersion, ?DateTimeImmutable $expiresAt): string
    {
        $locked = $this->lock($id);
        $assignment = $locked->assignment;
        $shift = $locked->shift;
        ServingRules::version($assignment->version, $expectedVersion);
        $at = ServingRules::instant($expiresAt);
        $now = $this->clock->now();
        if ($assignment->isCancelled() || !$shift->isOpen() || !($at > $now) || $at > $shift->startsAt) {
            throw VolunteerException::invalid('Invitation expiry must be in the future and no later than shift start.');
        }
        $this->activities->require(ShiftViews::of($shift));
        $this->withdraw($id, $now);
        $invitation = Invitation::issue($this->ids->uuid(), $id, $assignment->version, $shift->scheduleRevision, $at);
        $this->repository->insertInvitation($invitation);
        $this->event($id, $actorId, 'INVITED', 'Volunteer response requested.', $now);
        $this->notices->invited($locked->volunteer->accountId, $invitation->id);
        $this->audit->record($actorId, 'invitation.created', $invitation->id, $now);

        return $invitation->id;
    }

    /** The volunteer linked to `$accountId` accepts (confirming) or declines (cancelling) a live invitation. */
    public function respond(string $actorId, string $accountId, string $id, int $expectedVersion, ?string $decision): void
    {
        $before = $this->roster->invitation($id) ?? throw VolunteerException::missing('Invitation');
        $locked = $this->lock($before->assignmentId);
        $assignment = $locked->assignment;
        $shift = $locked->shift;
        if ($accountId !== $locked->volunteer->accountId || !$locked->volunteer->isActive()) {
            throw VolunteerException::forbidden();
        }
        $invitation = $this->repository->lockInvitation($id) ?? throw VolunteerException::missing('Invitation');
        ServingRules::version($invitation->version, $expectedVersion);
        $now = $this->clock->now();
        if (!$invitation->isPending() || !($invitation->expiresAt > $now) || $invitation->assignmentVersion !== $assignment->version
            || $invitation->shiftVersion !== $shift->scheduleRevision || $assignment->isCancelled() || !$shift->isOpen()) {
            throw VolunteerException::conflict('This invitation expired or the shift changed. Request a new invitation.');
        }
        $this->activities->require(ShiftViews::of($shift));
        if ('ACCEPTED' !== $decision && 'DECLINED' !== $decision) {
            throw VolunteerException::invalid('Choose accepted or declined.');
        }
        $this->repository->saveShift($shift->touch());
        $this->repository->saveInvitation($invitation->respond($decision, $now));
        $this->repository->saveAssignment($assignment->change('ACCEPTED' === $decision ? 'CONFIRMED' : 'CANCELLED'));
        $this->event($assignment->id, $actorId, $decision, 'Volunteer response.', $now);
        $this->audit->record($actorId, 'invitation.responded', $id, $now);
    }

    /** Expires up to `$limit` overdue invitations, releasing only proposed holds the invitation still describes. */
    public function expire(string $actorId, int $limit): int
    {
        if ($limit < 1 || $limit > 100) {
            throw VolunteerException::invalid('Process between 1 and 100 invitations.');
        }
        $now = $this->clock->now();
        $count = 0;
        foreach ($this->roster->expirable($now, $limit) as $candidate) {
            $locked = $this->lock($candidate->assignmentId);
            $invitation = $this->repository->lockInvitation($candidate->id) ?? throw VolunteerException::missing('Invitation');
            if (!$invitation->isPending() || $invitation->expiresAt > $now) {
                continue;
            }
            $this->repository->saveInvitation($invitation->respond('EXPIRED', $now));
            if ($locked->assignment->version === $invitation->assignmentVersion && $locked->shift->scheduleRevision === $invitation->shiftVersion
                && 'PROPOSED' === $locked->assignment->status) {
                $this->repository->saveAssignment($locked->assignment->change('CANCELLED'));
                $this->repository->saveShift($locked->shift->touch());
            }
            $this->event($invitation->assignmentId, $actorId, 'INVITATION_EXPIRED', 'Invitation hold released.', $now);
            $this->audit->record($actorId, 'invitation.expired', $invitation->id, $now);
            ++$count;
        }

        return $count;
    }

    /** Volunteer (person, activities, row), then shift, then assignment. */
    public function lock(string $assignmentId): LockedAssignment
    {
        $before = $this->roster->assignment($assignmentId) ?? throw VolunteerException::missing('Assignment');
        $volunteer = $this->locks->lock($before->volunteerId);
        $shift = $this->repository->lockShift($before->shiftId) ?? throw VolunteerException::missing('Shift');
        $assignment = $this->repository->lockAssignment($assignmentId) ?? throw VolunteerException::missing('Assignment');

        return new LockedAssignment($volunteer, $shift, $assignment);
    }

    private static function validateSource(ShiftDraft $draft): void
    {
        ServingRules::id($draft->requestId);
        ServingRules::id($draft->teamId);
        ServingRules::id($draft->roleId);
        if (null === $draft->sourceKind || '' === $draft->sourceKind || mb_strlen($draft->sourceKind) > 40
            || (('STANDALONE' === $draft->sourceKind) !== (null === $draft->sourceId))) {
            throw VolunteerException::invalid('Choose a valid activity source.');
        }
    }

    private function createLocked(string $actorId, ?ShiftDraft $draft): string
    {
        if (null === $draft) {
            throw VolunteerException::invalid('A shift draft is required.');
        }
        $requestId = ServingRules::id($draft->requestId);
        $teamId = ServingRules::id($draft->teamId);
        $roleId = ServingRules::id($draft->roleId);
        $team = $this->teamRepository->lock($teamId);
        if (null === $team || !$team->active) {
            throw VolunteerException::missing('Active team');
        }
        $hash = Fingerprint::of(
            $teamId,
            $roleId,
            $draft->sourceKind,
            $draft->sourceId,
            Fingerprint::instant(ServingRules::instant($draft->startsAt)),
            Fingerprint::instant(ServingRules::instant($draft->endsAt)),
            (string) $draft->positions,
        );
        $previous = $this->roster->shiftRequest($requestId);
        if (null !== $previous) {
            if ($previous->requestHash !== $hash) {
                throw VolunteerException::conflict('This request identifier was already used for another shift.');
            }

            return $previous->id;
        }
        $role = $this->teams->role($roleId);
        if (null === $role || $role->teamId !== $teamId || !$role->active) {
            throw VolunteerException::missing('Active team role');
        }
        $shift = Shift::plan($this->ids->uuid(), $requestId, $hash, $teamId, $roleId, $draft->sourceKind, $draft->sourceId, $draft->startsAt, $draft->endsAt, $draft->positions);
        $shift = $shift->withSourceRevision($this->activities->revision(ShiftViews::of($shift)));
        $this->activities->require(ShiftViews::of($shift));
        $this->repository->insertShift($shift);
        $this->teamRepository->save($team->touch());
        $this->audit->record($actorId, 'shift.created', $shift->id, $this->clock->now());

        return $shift->id;
    }

    /** @return list<AssignmentView> the shift's non-cancelled assignments */
    private function live(string $shiftId): array
    {
        return array_values(array_filter($this->roster->assignments($shiftId), static fn (AssignmentView $a): bool => 'CANCELLED' !== $a->status));
    }

    /** Withdraws the assignment's pending invitations: the commitment they asked about is changing. */
    private function withdraw(string $assignmentId, DateTimeImmutable $now): void
    {
        foreach ($this->roster->invitations($assignmentId) as $view) {
            if ('PENDING' !== $view->status) {
                continue;
            }
            $invitation = $this->repository->lockInvitation($view->id) ?? throw VolunteerException::missing('Invitation');
            if ($invitation->isPending()) {
                $this->repository->saveInvitation($invitation->respond('WITHDRAWN', $now));
            }
        }
    }

    private function account(string $volunteerId): ?string
    {
        return $this->volunteers->volunteer($volunteerId)?->accountId;
    }

    private function event(string $assignmentId, string $actorId, string $action, string $reason, DateTimeImmutable $at): void
    {
        $this->repository->event($this->ids->uuid(), $assignmentId, $actorId, $action, $reason, $at);
    }
}
