<?php

declare(strict_types=1);

namespace App\Volunteering\Application\Contract;

use DateTimeImmutable;

/** Java `RosterReader` and `MemberDutyReader` beyond what readiness needs. Views carry every stored field. */
interface RosterDirectory extends RosterReader
{
    public function shiftRequest(string $requestId): ?ShiftView;

    /** @return list<ShiftView> overlapping the interval, by start then id */
    public function shifts(DateTimeImmutable $from, DateTimeImmutable $until, ?string $teamId, int $limit, int $offset): array;

    public function shiftCount(DateTimeImmutable $from, DateTimeImmutable $until, ?string $teamId): int;

    public function assignmentRequest(string $requestId): ?AssignmentView;

    public function invitation(string $id): ?InvitationRecord;

    /** @return list<InvitationRecord> pending first, then latest expiry, at most 100 */
    public function invitations(string $assignmentId): array;

    /** @return list<InvitationRecord> pending invitations expired at the instant, earliest first */
    public function expirable(DateTimeImmutable $at, int $limit): array;

    /** @return list<InvitationRecord> the volunteer's invitations, pending first, then latest expiry */
    public function myInvitations(string $volunteerId, int $limit, int $offset): array;

    /** @return list<AssignmentEvent> newest first */
    public function events(string $assignmentId, int $limit, int $offset): array;

    /** @return list<AssignmentView> every assignment of the volunteer (cancelled ones too) overlapping the interval */
    public function duties(string $volunteerId, DateTimeImmutable $from, DateTimeImmutable $until, int $limit, int $offset): array;
}
