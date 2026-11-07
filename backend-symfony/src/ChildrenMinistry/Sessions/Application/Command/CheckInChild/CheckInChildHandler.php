<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Sessions\Application\Command\CheckInChild;

use App\ChildrenMinistry\Sessions\Application\AdmissionPolicy;
use App\ChildrenMinistry\Sessions\Application\AttendanceView;
use App\ChildrenMinistry\Sessions\Application\Contract\AttendanceReader;
use App\ChildrenMinistry\Sessions\Application\SessionOperations;
use App\ChildrenMinistry\Sessions\Application\StaffScope;
use App\ChildrenMinistry\Sessions\Domain\Attendance;
use App\ChildrenMinistry\Sessions\Domain\AttendanceEvent;
use App\ChildrenMinistry\Sessions\Domain\AttendanceSegment;
use App\ChildrenMinistry\Sessions\Domain\Repository\AttendanceRepository;
use App\ChildrenMinistry\Sessions\Domain\Repository\SessionRepository;
use App\ChildrenMinistry\Shared\Application\Contract\ChildrenAudit;
use App\ChildrenMinistry\Shared\Application\ParticipantAccess;
use App\ChildrenMinistry\Shared\Domain\ChildrenException;
use App\IdentityAccess\Application\Contract\PermissionChecker;
use App\Shared\Application\CommandHandler;
use App\Shared\Application\IdGenerator;
use Psr\Clock\ClockInterface;

/**
 * Checks a child in. Lock order: the child (person row, then guard row), then the session row. Retrying with the
 * same request id returns the same visit; reusing it for another child or session is a 409.
 */
final readonly class CheckInChildHandler implements CommandHandler
{
    public function __construct(
        private PermissionChecker $authorization,
        private ChildrenAudit $audit,
        private ClockInterface $clock,
        private IdGenerator $ids,
        private ParticipantAccess $participants,
        private StaffScope $scope,
        private SessionRepository $sessions,
        private AttendanceReader $attendance,
        private AttendanceRepository $repository,
        private SessionOperations $operations,
        private AdmissionPolicy $admission,
    ) {
    }

    public function handle(CheckInChildCommand $command): AttendanceView
    {
        $this->authorization->requireActorPermission($command->actorId, 'children.station');
        $this->participants->lockChild($command->childId);
        $session = $this->sessions->ministrySessionForUpdate($command->sessionId) ?? throw ChildrenException::missing('MinistrySession');
        $this->scope->require($command->actorId, $session->id);

        $replay = $this->attendance->arrival($command->actorId, $command->requestId);
        if (null !== $replay) {
            $segments = $this->attendance->segments($replay->id);
            if ($replay->childId !== $command->childId || [] === $segments || $segments[0]->sessionId !== $command->sessionId) {
                throw ChildrenException::conflict('Arrival request ID was already used for another arrival.');
            }

            return AttendanceView::from($replay);
        }

        $now = $this->clock->now();
        $session->requireArrivalWindow($now);
        $this->operations->requireSpace($session, $now);
        $evidence = $this->admission->require($session, $command->childId, $now);

        $visit = Attendance::arrive($this->ids->uuid(), $command->childId, $session->id, $now, $command->actorId, $command->requestId);
        $this->repository->insertAttendance($visit);
        $this->repository->insertAttendanceSegment(new AttendanceSegment($this->ids->uuid(), $visit->id, $session->id, $evidence->enrolmentId, $evidence->assignmentId, $evidence->consentId, $now, null));
        $this->repository->insertAttendanceEvent(new AttendanceEvent($this->ids->uuid(), $visit->id, $command->actorId, 'ARRIVED', '', $now));
        $this->audit->record($command->actorId, 'child.checked.in', $visit->id, '', $now);

        return AttendanceView::from($visit);
    }
}
