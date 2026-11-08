<?php

declare(strict_types=1);

namespace App\Volunteering\Application\Command\ApproveServingRole;

use App\IdentityAccess\Application\Contract\PermissionChecker;
use App\Shared\Application\CommandHandler;
use App\Shared\Application\IdGenerator;
use App\Volunteering\Application\Contract\ServingAudit;
use App\Volunteering\Application\ServingPermissions;
use App\Volunteering\Application\TeamLocks;
use App\Volunteering\Application\VolunteerLocks;
use App\Volunteering\Domain\Repository\TeamRepository;
use App\Volunteering\Domain\RoleApproval;
use App\Volunteering\Domain\VolunteerException;
use Psr\Clock\ClockInterface;

/**
 * Java `TeamManagement.approval`: approves a volunteer for a role of the team for a period that overlaps none of their
 * unrevoked approvals of that role. Like Java it needs neither an active team nor an active role. Lock order: the
 * volunteer ({@see VolunteerLocks}: person, linked activities, volunteer row), then the team row.
 */
final readonly class ApproveServingRoleHandler implements CommandHandler
{
    public function __construct(
        private PermissionChecker $auth,
        private VolunteerLocks $volunteers,
        private TeamLocks $locks,
        private TeamRepository $teams,
        private ServingAudit $audit,
        private IdGenerator $ids,
        private ClockInterface $clock,
    ) {
    }

    public function handle(ApproveServingRoleCommand $command): string
    {
        $this->auth->requireActorPermission($command->actorId, ServingPermissions::MANAGE);
        $volunteer = $this->volunteers->lock($command->volunteerId);
        $team = $this->locks->team($command->teamId, $command->expectedVersion);
        $role = $this->teams->findRole($command->roleId);
        if (null === $role || $role->teamId !== $team->id) {
            throw VolunteerException::missing('Team role');
        }
        $approval = RoleApproval::grant($this->ids->uuid(), $role->id, $volunteer->id, $command->validFrom, $command->validUntil);
        if ($approval->overlapsAny(...$this->teams->approvalWindows($role->id, $volunteer->id))) {
            throw VolunteerException::conflict('A role approval already covers part of this interval.');
        }
        $this->teams->addApproval($approval);
        $this->teams->save($team->touch());
        $this->audit->record($command->actorId, 'team.role.approved', $approval->id, $this->clock->now());

        return $approval->id;
    }
}
