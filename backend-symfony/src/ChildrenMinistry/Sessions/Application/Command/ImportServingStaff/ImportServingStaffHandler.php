<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Sessions\Application\Command\ImportServingStaff;

use App\ChildrenMinistry\Sessions\Application\Command\SetSessionStaff\SetSessionStaffCommand;
use App\ChildrenMinistry\Sessions\Application\Command\SetSessionStaff\SetSessionStaffHandler;
use App\ChildrenMinistry\Sessions\Application\Contract\SessionReader;
use App\ChildrenMinistry\Sessions\Application\Contract\VolunteerStaffLinkWriter;
use App\ChildrenMinistry\Sessions\Domain\MinistrySession;
use App\ChildrenMinistry\Sessions\Domain\Repository\SessionRepository;
use App\ChildrenMinistry\Sessions\Domain\StaffRole;
use App\ChildrenMinistry\Shared\Domain\ChildrenException;
use App\IdentityAccess\Application\Contract\PermissionChecker;
use App\People\Application\Contract\PersonDirectory;
use App\Shared\Application\CommandHandler;
use App\Volunteering\Application\Contract\StaffingAssessment;
use App\Volunteering\Application\Contract\StaffingDirectory;
use App\Volunteering\Application\Contract\StaffingUnavailable;

/**
 * Brings a confirmed, ready serving assignment onto the session. Lock order: the person, then the session, then
 * the worker; readiness is assessed once to find the person and again under the lock to decide.
 */
final readonly class ImportServingStaffHandler implements CommandHandler
{
    public function __construct(
        private PermissionChecker $authorization,
        private PersonDirectory $people,
        private SessionReader $sessions,
        private SessionRepository $repository,
        private StaffingDirectory $staffing,
        private VolunteerStaffLinkWriter $links,
        private SetSessionStaffHandler $staff,
    ) {
    }

    public function handle(ImportServingStaffCommand $command): void
    {
        $this->authorization->requireActorPermission($command->actorId, 'children.manage');
        $before = $this->sessions->ministrySession($command->sessionId) ?? throw ChildrenException::missing('Session');
        $this->people->lock($this->assess($command, $before)->personId);
        $session = $this->repository->ministrySessionForUpdate($command->sessionId) ?? throw ChildrenException::missing('Session');
        $candidate = $this->assess($command, $session);
        if (!$candidate->ready) {
            throw ChildrenException::conflict(implode(' ', $candidate->issues));
        }
        if (null === $this->sessions->worker($candidate->personId)) {
            throw ChildrenException::conflict('Register and verify this person as a Children Ministry worker in Spaces & team first.');
        }
        $role = StaffRole::tryFrom($command->responsibility) ?? throw ChildrenException::invalid('Choose leader or assistant.');

        $this->links->save($session->id, $candidate->personId, $command->assignmentId, $role->value);
        $this->staff->handle(new SetSessionStaffCommand($command->actorId, $session->id, $command->expectedVersion, $candidate->personId, $role, $command->present));
    }

    private function assess(ImportServingStaffCommand $command, MinistrySession $session): StaffingAssessment
    {
        try {
            return $this->staffing->assess($command->assignmentId, $session->id, $command->responsibility, $session->startsAt, $session->endsAt);
        } catch (StaffingUnavailable) {
            throw ChildrenException::conflict('The serving assignment is no longer available. Refresh the roster.');
        }
    }
}
