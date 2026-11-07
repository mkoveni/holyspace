<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Sessions\Application\Command\SetSessionStatus;

use App\ChildrenMinistry\Sessions\Application\Contract\AttendanceReader;
use App\ChildrenMinistry\Sessions\Application\Contract\SessionIncidents;
use App\ChildrenMinistry\Sessions\Application\SessionOperations;
use App\ChildrenMinistry\Sessions\Domain\Repository\SessionRepository;
use App\ChildrenMinistry\Sessions\Domain\SessionStatus;
use App\ChildrenMinistry\Shared\Application\Contract\ChildrenAudit;
use App\ChildrenMinistry\Shared\Domain\ChildrenException;
use App\ChildrenMinistry\Shared\Domain\ChildrenRules;
use App\IdentityAccess\Application\Contract\PermissionChecker;
use App\Shared\Application\ChurchCalendar;
use App\Shared\Application\CommandHandler;
use Psr\Clock\ClockInterface;

/** Closing or cancelling needs an empty room; closing with open incidents needs an acknowledged review; opening needs staff. */
final readonly class SetSessionStatusHandler implements CommandHandler
{
    public function __construct(
        private PermissionChecker $authorization,
        private ChildrenAudit $audit,
        private ClockInterface $clock,
        private SessionRepository $repository,
        private SessionOperations $operations,
        private AttendanceReader $attendance,
        private SessionIncidents $incidents,
        private ChurchCalendar $calendar,
    ) {
    }

    public function handle(SetSessionStatusCommand $command): void
    {
        $this->authorization->requireActorPermission($command->actorId, 'children.manage');
        $session = $this->repository->ministrySessionForUpdate($command->sessionId) ?? throw ChildrenException::missing('MinistrySession');
        $now = $this->clock->now();
        $reason = ChildrenRules::text($command->reason, 500);

        if ($command->status->terminal() && $this->attendance->occupancy($session->id) > 0) {
            throw ChildrenException::conflict('Collect or transfer all children before closing or cancelling.');
        }
        if (SessionStatus::Closed === $command->status && $this->incidents->unresolved($session->id, $this->calendar->today()) > 0) {
            $this->authorization->requirePermission('children.incidents.manage');
            if (!$command->acknowledgeIncidents) {
                throw ChildrenException::conflict('Review and acknowledge outstanding incident follow-ups before closing this session.');
            }
            $this->audit->record($command->actorId, 'session.incidents.acknowledged', $session->id, 'Outstanding follow-up continues after closure.', $now);
        }
        if (SessionStatus::Open === $command->status && $this->operations->view($session, $now)->staffingNeedsReview) {
            throw ChildrenException::conflict('Opening requires eligible present staff, including a leader, and sufficient capacity.');
        }

        $this->repository->updateMinistrySession($session->withStatus($command->status, $command->expectedVersion));
        $this->audit->record($command->actorId, 'session.status.changed', $session->id, $reason, $now);
    }
}
