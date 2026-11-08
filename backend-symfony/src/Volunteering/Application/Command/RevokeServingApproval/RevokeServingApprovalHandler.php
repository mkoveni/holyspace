<?php

declare(strict_types=1);

namespace App\Volunteering\Application\Command\RevokeServingApproval;

use App\IdentityAccess\Application\Contract\PermissionChecker;
use App\Shared\Application\CommandHandler;
use App\Volunteering\Application\Contract\ServingAudit;
use App\Volunteering\Application\ServingPermissions;
use App\Volunteering\Application\TeamLocks;
use App\Volunteering\Application\VolunteerLocks;
use App\Volunteering\Domain\Repository\TeamRepository;
use App\Volunteering\Domain\VolunteerException;
use Psr\Clock\ClockInterface;

/**
 * Java `TeamManagement.revoke`: ends a role approval or a membership the volunteer holds on the team. The record stays;
 * an already revoked one keeps its first revocation instant, but the team version still moves and the audit row is
 * written, as in Java. Lock order: the volunteer ({@see VolunteerLocks}), then the team row.
 */
final readonly class RevokeServingApprovalHandler implements CommandHandler
{
    public function __construct(
        private PermissionChecker $auth,
        private VolunteerLocks $volunteers,
        private TeamLocks $locks,
        private TeamRepository $teams,
        private ServingAudit $audit,
        private ClockInterface $clock,
    ) {
    }

    public function handle(RevokeServingApprovalCommand $command): void
    {
        $this->auth->requireActorPermission($command->actorId, ServingPermissions::MANAGE);
        $volunteer = $this->volunteers->lock($command->volunteerId);
        $team = $this->locks->team($command->teamId, $command->expectedVersion);
        $now = $this->clock->now();
        if ($command->roleApproval) {
            if (!$this->teams->hasApproval($team->id, $volunteer->id, $command->recordId)) {
                throw VolunteerException::missing('Role approval');
            }
            $this->teams->revokeApproval($command->recordId, $now);
        } else {
            if (!$this->teams->hasMembership($team->id, $volunteer->id, $command->recordId)) {
                throw VolunteerException::missing('Membership');
            }
            $this->teams->revokeMembership($command->recordId, $now);
        }
        $this->teams->save($team->touch());
        $this->audit->record($command->actorId, $command->roleApproval ? 'team.approval.revoked' : 'team.membership.revoked', $command->recordId, $now);
    }
}
