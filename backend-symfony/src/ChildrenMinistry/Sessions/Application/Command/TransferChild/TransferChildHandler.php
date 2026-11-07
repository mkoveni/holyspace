<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Sessions\Application\Command\TransferChild;

use App\ChildrenMinistry\Sessions\Application\AdmissionPolicy;
use App\ChildrenMinistry\Sessions\Application\AttendanceView;
use App\ChildrenMinistry\Sessions\Application\Contract\AttendanceReader;
use App\ChildrenMinistry\Sessions\Application\SessionOperations;
use App\ChildrenMinistry\Sessions\Domain\AttendanceEvent;
use App\ChildrenMinistry\Sessions\Domain\AttendanceSegment;
use App\ChildrenMinistry\Sessions\Domain\Repository\AttendanceRepository;
use App\ChildrenMinistry\Sessions\Domain\Repository\SessionRepository;
use App\ChildrenMinistry\Shared\Application\Contract\ChildrenAudit;
use App\ChildrenMinistry\Shared\Application\ParticipantAccess;
use App\ChildrenMinistry\Shared\Domain\ChildrenException;
use App\ChildrenMinistry\Shared\Domain\ChildrenRules;
use App\IdentityAccess\Application\Contract\PermissionChecker;
use App\Shared\Application\CommandHandler;
use App\Shared\Application\IdGenerator;
use Psr\Clock\ClockInterface;

/**
 * Moves a present child to another session without ending the visit. Lock order: the child, both sessions in id
 * order, then the attendance row. The target must be open for arrivals with space, and admit the child afresh.
 */
final readonly class TransferChildHandler implements CommandHandler
{
    public function __construct(
        private PermissionChecker $authorization,
        private ChildrenAudit $audit,
        private ClockInterface $clock,
        private IdGenerator $ids,
        private ParticipantAccess $participants,
        private SessionRepository $sessions,
        private AttendanceReader $attendance,
        private AttendanceRepository $repository,
        private SessionOperations $operations,
        private AdmissionPolicy $admission,
    ) {
    }

    public function handle(TransferChildCommand $command): AttendanceView
    {
        $this->authorization->requireActorPermission($command->actorId, 'children.supervise');
        $source = $this->attendance->attendance($command->attendanceId) ?? throw ChildrenException::missing('Attendance');
        $this->participants->lockChild($source->childId);
        $ids = array_unique([$source->sessionId, $command->targetSessionId]);
        sort($ids, \SORT_STRING);
        foreach ($ids as $id) {
            $this->sessions->ministrySessionForUpdate($id) ?? throw ChildrenException::missing('Session');
        }
        $value = $this->repository->attendanceForUpdate($command->attendanceId) ?? throw ChildrenException::missing('Attendance');
        if ($value->sessionId !== $source->sessionId) {
            throw ChildrenException::conflict('Attendance moved. Reload before transferring.');
        }
        $target = $this->sessions->ministrySessionForUpdate($command->targetSessionId) ?? throw ChildrenException::missing('MinistrySession');

        $now = $this->clock->now();
        $target->requireArrivalWindow($now);
        $this->operations->requireSpace($target, $now);
        $evidence = $this->admission->require($target, $value->childId, $now);
        $reason = ChildrenRules::text($command->reason, 500);

        $updated = $value->transfer($target->id, $command->expectedVersion);
        $this->repository->closeSegment($value->id, $now);
        $this->repository->updateAttendance($updated);
        $this->repository->insertAttendanceSegment(new AttendanceSegment($this->ids->uuid(), $value->id, $target->id, $evidence->enrolmentId, $evidence->assignmentId, $evidence->consentId, $now, null));
        $this->repository->insertAttendanceEvent(new AttendanceEvent($this->ids->uuid(), $value->id, $command->actorId, 'TRANSFERRED', $reason, $now));
        $this->audit->record($command->actorId, 'child.transferred', $value->id, $reason, $now);

        return AttendanceView::from($updated);
    }
}
