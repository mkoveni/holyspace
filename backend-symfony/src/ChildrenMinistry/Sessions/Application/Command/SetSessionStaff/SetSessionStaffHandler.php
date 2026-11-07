<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Sessions\Application\Command\SetSessionStaff;

use App\ChildrenMinistry\Sessions\Application\Contract\SessionReader;
use App\ChildrenMinistry\Sessions\Application\SessionOperations;
use App\ChildrenMinistry\Sessions\Domain\Repository\SessionRepository;
use App\ChildrenMinistry\Sessions\Domain\SessionStaff;
use App\ChildrenMinistry\Sessions\Domain\SessionStatus;
use App\ChildrenMinistry\Shared\Application\Contract\ChildrenAudit;
use App\ChildrenMinistry\Shared\Domain\ChildrenException;
use App\ChildrenMinistry\Shared\Domain\ChildrenRules;
use App\IdentityAccess\Application\Contract\PermissionChecker;
use App\Shared\Application\CommandHandler;
use Psr\Clock\ClockInterface;

/** Assigns a worker to a session or marks them present or absent; an open session left understaffed needs review. */
final readonly class SetSessionStaffHandler implements CommandHandler
{
    public function __construct(
        private PermissionChecker $authorization,
        private ChildrenAudit $audit,
        private ClockInterface $clock,
        private SessionRepository $repository,
        private SessionReader $sessions,
        private SessionOperations $operations,
    ) {
    }

    public function handle(SetSessionStaffCommand $command): void
    {
        $this->authorization->requireActorPermission($command->actorId, 'children.manage');
        $session = $this->repository->ministrySessionForUpdate($command->sessionId) ?? throw ChildrenException::missing('MinistrySession');
        if ($session->status->terminal()) {
            throw ChildrenException::conflict('Terminal session staffing cannot change.');
        }
        if (null === $this->repository->workerForUpdate($command->workerId)) {
            throw ChildrenException::missing('Worker');
        }
        if ($command->present && $this->sessions->presentElsewhere($command->workerId, $session->id, $session->startsAt, $session->endsAt)) {
            throw ChildrenException::conflict('Worker is already present in an overlapping session. Mark them absent there first.');
        }
        ChildrenRules::version($session->version, $command->expectedVersion);

        $now = $this->clock->now();
        $this->repository->saveSessionStaff(new SessionStaff($session->id, $command->workerId, $command->role, $command->present));
        $status = SessionStatus::Open === $session->status && $this->operations->view($session, $now)->staffingNeedsReview ? SessionStatus::NeedsReview : $session->status;
        $this->repository->updateMinistrySession($session->withStatus($status, $command->expectedVersion));
        $this->audit->record($command->actorId, 'session.staff.changed', $session->id, sprintf('%s:%s:present=%s', $command->workerId, $command->role->value, $command->present ? 'true' : 'false'), $now);
    }
}
