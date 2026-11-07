<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Sessions\Application\Command\IssuePickupLabel;

use App\ChildrenMinistry\Sessions\Application\Contract\AttendanceReader;
use App\ChildrenMinistry\Sessions\Application\Contract\PickupTokens;
use App\ChildrenMinistry\Sessions\Application\LabelIssue;
use App\ChildrenMinistry\Sessions\Application\StaffScope;
use App\ChildrenMinistry\Sessions\Domain\AttendanceEvent;
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

/** Issues the one-time pickup code, valid 24 hours. Only its hash is stored; replacing a label needs a reason. */
final readonly class IssuePickupLabelHandler implements CommandHandler
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
        private PickupTokens $tokens,
    ) {
    }

    public function handle(IssuePickupLabelCommand $command): LabelIssue
    {
        $this->authorization->requireActorPermission($command->actorId, 'children.station');
        $source = $this->attendance->attendance($command->attendanceId) ?? throw ChildrenException::missing('Attendance');
        $this->participants->lockChild($source->childId);
        $session = $this->sessions->ministrySessionForUpdate($source->sessionId) ?? throw ChildrenException::missing('MinistrySession');
        $this->scope->require($command->actorId, $session->id);
        $value = $this->repository->attendanceForUpdate($command->attendanceId) ?? throw ChildrenException::missing('Attendance');
        if ($value->sessionId !== $session->id) {
            throw ChildrenException::conflict('Attendance moved; reload it first.');
        }

        $now = $this->clock->now();
        $token = $this->tokens->issue();
        $updated = $value->issueLabel($this->tokens->hash($token), $now->modify('+86400 seconds'), $command->expectedVersion, $command->replacementReason);
        $this->repository->updateAttendance($updated);
        $reason = ChildrenRules::optional($command->replacementReason, 500);
        $this->audit->record($command->actorId, 'pickup.label.issued', $value->id, $reason, $now);
        $this->repository->insertAttendanceEvent(new AttendanceEvent($this->ids->uuid(), $value->id, $command->actorId, 'LABEL_ISSUED', $reason, $now));

        return new LabelIssue($value->id, $updated->labelVersion, $token, $updated->labelExpiresAt ?? $now, $updated->version);
    }
}
