<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Sessions\Application;

use App\ChildrenMinistry\Participation\Application\Contract\ParticipationReader;
use App\ChildrenMinistry\Safeguarding\Application\Contract\SafeguardingReader;
use App\ChildrenMinistry\Sessions\Application\Contract\AttendanceReader;
use App\ChildrenMinistry\Sessions\Application\Contract\PickupTokens;
use App\ChildrenMinistry\Sessions\Domain\AttendanceEvent;
use App\ChildrenMinistry\Sessions\Domain\Repository\AttendanceRepository;
use App\ChildrenMinistry\Sessions\Domain\Repository\SessionRepository;
use App\ChildrenMinistry\Shared\Application\Contract\ChildrenAudit;
use App\ChildrenMinistry\Shared\Application\ParticipantAccess;
use App\ChildrenMinistry\Shared\Domain\ChildrenException;
use App\ChildrenMinistry\Shared\Domain\ChildrenRules;
use App\IdentityAccess\Application\Contract\PermissionChecker;
use App\Shared\Application\ChurchCalendar;
use App\Shared\Application\IdGenerator;
use Psr\Clock\ClockInterface;

/**
 * Releasing a child to a collector. Runs inside the caller's transaction. Lock order: the child and collector
 * (people by id, then the child guard), then the session row, then the attendance row.
 */
final readonly class Handover
{
    public function __construct(
        private PermissionChecker $authorization,
        private ParticipantAccess $participants,
        private StaffScope $scope,
        private AttendanceReader $attendance,
        private AttendanceRepository $repository,
        private SessionRepository $sessions,
        private ParticipationReader $participation,
        private SafeguardingReader $safeguarding,
        private PickupTokens $tokens,
        private ChildrenAudit $audit,
        private ClockInterface $clock,
        private ChurchCalendar $calendar,
        private IdGenerator $ids,
    ) {
    }

    public function collect(string $actorId, string $attendanceId, int $expectedVersion, string $requestId, string $collectorId, bool $identityVerified, ?string $code, ?string $exceptionReason): HandoverOutcome
    {
        $this->authorization->requireActorPermission($actorId, null === $exceptionReason ? 'children.station' : 'children.supervise');
        $source = $this->attendance->attendance($attendanceId) ?? throw ChildrenException::missing('Attendance');
        $this->participants->lockChild($source->childId, $collectorId);
        $session = $this->sessions->ministrySessionForUpdate($source->sessionId) ?? throw ChildrenException::missing('Session');
        $this->scope->require($actorId, $session->id);
        $value = $this->repository->attendanceForUpdate($attendanceId) ?? throw ChildrenException::missing('Attendance');
        if ($session->id !== $value->sessionId) {
            throw ChildrenException::conflict('Attendance moved; reload it first.');
        }
        if (null !== $value->checkedOutAt) {
            if ($requestId === $value->checkoutRequestId && $collectorId === $value->collectorId && $actorId === $value->checkedOutBy) {
                return HandoverOutcome::collected(AttendanceView::from($value));
            }
            throw ChildrenException::conflict('Child has already been collected.');
        }
        ChildrenRules::version($value->version, $expectedVersion);
        $now = $this->clock->now();
        if (!$identityVerified) {
            throw ChildrenException::invalid('Confirm collector identity before handover.');
        }
        ChildrenRules::adult($this->participants->available($collectorId)->birthDate, $this->calendar->today());
        $class = $this->participation->ministryClass($session->classId) ?? throw ChildrenException::missing('Class');

        $grantId = null;
        foreach ($this->safeguarding->validAuthorizations($class->programmeId, $value->childId, $collectorId, $now) as $grant) {
            $authority = $this->safeguarding->guardianAuthority($grant->authorityId);
            if (null !== $authority && null === $authority->revokedAt && ChildrenRules::covers($authority->validFrom, $authority->validUntil, $now, $now)) {
                $grantId = $grant->id;
                break;
            }
        }
        $grantId ??= throw ChildrenException::conflict('A current pickup authorization and guardian authority are required.');

        if (null === $exceptionReason) {
            if ($value->throttled($now)) {
                return HandoverOutcome::refused(ChildrenException::throttled('Wait before trying another pickup code.'));
            }
            $valid = null !== $value->labelHash && null !== $value->labelExpiresAt && $now < $value->labelExpiresAt && $this->tokens->matches($code, $value->labelHash);
            if (!$valid) {
                $failed = $value->failedAttempt($now);
                $this->repository->updateAttendance($failed);
                $this->audit->record($actorId, 'pickup.label.rejected', $value->id, '', $now);

                return HandoverOutcome::refused($failed->throttled($now)
                    ? ChildrenException::throttled('Wait before trying another pickup code.')
                    : ChildrenException::invalid('Pickup code is invalid or expired. Reload attendance before retrying.'));
            }
        } else {
            ChildrenRules::text($exceptionReason, 500);
        }

        $updated = $value->collect($collectorId, $actorId, $requestId, $grantId, true, $exceptionReason, $expectedVersion, $now);
        $this->repository->updateAttendance($updated);
        $this->repository->closeSegment($value->id, $now);
        $action = null === $exceptionReason ? 'COLLECTED' : 'SUPERVISOR_COLLECTION';
        $reason = ChildrenRules::optional($exceptionReason, 500);
        $this->repository->insertAttendanceEvent(new AttendanceEvent($this->ids->uuid(), $value->id, $actorId, $action, $reason, $now));
        $this->audit->record($actorId, 'attendance.'.strtolower($action), $value->id, $reason, $now);

        return HandoverOutcome::collected(AttendanceView::from($updated));
    }
}
