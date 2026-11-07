<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Sessions\Application\Command\ApplyTeamPlan;

use App\ChildrenMinistry\Sessions\Application\TeamPlanning;
use App\ChildrenMinistry\Sessions\Domain\Repository\SessionRepository;
use App\ChildrenMinistry\Sessions\Domain\Repository\TeamAssignmentRepository;
use App\ChildrenMinistry\Sessions\Domain\SessionStaff;
use App\ChildrenMinistry\Shared\Application\Contract\ChildrenAudit;
use App\ChildrenMinistry\Shared\Domain\ChildrenException;
use App\ChildrenMinistry\Shared\Domain\ChildrenRules;
use App\IdentityAccess\Application\Contract\PermissionChecker;
use App\Shared\Application\CommandHandler;
use Psr\Clock\ClockInterface;

/**
 * Applies a reviewed plan atomically. Lock order: the sessions in UUID order, then the workers in UUID order; the plan is
 * then re-previewed under the locks, so a concurrent plan for the same worker either waits and sees the clash or fails.
 */
final readonly class ApplyTeamPlanHandler implements CommandHandler
{
    public function __construct(
        private PermissionChecker $authorization,
        private TeamPlanning $planning,
        private SessionRepository $sessions,
        private TeamAssignmentRepository $assignments,
        private ChildrenAudit $audit,
        private ClockInterface $clock,
    ) {
    }

    public function handle(ApplyTeamPlanCommand $command): null
    {
        $this->authorization->requireActorPermission($command->actorId, 'children.manage');
        $draft = $command->draft;
        $this->planning->validate($draft);
        $reason = ChildrenRules::text($command->reason, 500);

        $ids = array_map(static fn ($target): string => $target->sessionId, $draft->targets);
        sort($ids, \SORT_STRING);
        $locked = [];
        foreach ($ids as $id) {
            $locked[] = $this->sessions->ministrySessionForUpdate($id) ?? throw ChildrenException::missing('Session');
        }
        $workers = array_values(array_unique(array_filter([$draft->workerId, $draft->replaceWorkerId], static fn (?string $id): bool => null !== $id)));
        sort($workers, \SORT_STRING);
        foreach ($workers as $worker) {
            $this->sessions->workerForUpdate($worker) ?? throw ChildrenException::missing('Worker');
        }

        $preview = $this->planning->preview($draft);
        if ([] !== $preview->blockers) {
            throw ChildrenException::conflict(implode(' ', $preview->blockers));
        }
        $now = $this->clock->now();
        foreach ($locked as $session) {
            if ($session->startsAt <= $now) {
                throw ChildrenException::conflict('A selected session has started. Reload the planning workspace.');
            }
        }
        foreach ($locked as $session) {
            if (null !== $draft->replaceWorkerId) {
                $this->assignments->removeAssignment($session->id, $draft->replaceWorkerId);
            }
            $this->sessions->saveSessionStaff(new SessionStaff($session->id, $draft->workerId, $draft->role, false));
            $this->sessions->updateMinistrySession($session->withStatus($session->status, $session->version));
            $this->audit->record($command->actorId, 'session.team.planned', $session->id, $reason, $now);
        }

        return null;
    }
}
