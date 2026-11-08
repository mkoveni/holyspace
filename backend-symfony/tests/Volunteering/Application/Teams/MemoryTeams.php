<?php

declare(strict_types=1);

namespace App\Tests\Volunteering\Application\Teams;

use App\Volunteering\Domain\Repository\TeamRepository;
use App\Volunteering\Domain\RoleApproval;
use App\Volunteering\Domain\ServingRole;
use App\Volunteering\Domain\Team;
use App\Volunteering\Domain\TeamMembership;
use DateTimeImmutable;

final class MemoryTeams implements TeamRepository
{
    /** @var array<string, Team> */
    public array $teams = [];

    /** @var array<string, ServingRole> */
    public array $roles = [];

    /** @var list<TeamMembership> */
    public array $memberships = [];

    /** @var list<RoleApproval> */
    public array $approvals = [];

    /** @var array<string, DateTimeImmutable> record id => revocation instant */
    public array $revoked = [];

    public function __construct(private readonly LockJournal $journal)
    {
    }

    public function lock(string $id): ?Team
    {
        $this->journal->entries[] = 'team:'.$id;

        return $this->teams[$id] ?? null;
    }

    public function insert(Team $team): void
    {
        $this->teams[$team->id] = $team;
    }

    public function save(Team $team): void
    {
        $this->teams[$team->id] = $team;
    }

    public function findRole(string $id): ?ServingRole
    {
        return $this->roles[$id] ?? null;
    }

    public function addRole(ServingRole $role): void
    {
        $this->roles[$role->id] = $role;
    }

    public function saveRole(ServingRole $role): void
    {
        $this->roles[$role->id] = $role;
    }

    public function membershipWindows(string $teamId, string $volunteerId): array
    {
        $windows = [];
        foreach ($this->memberships as $membership) {
            if ($membership->teamId === $teamId && $membership->volunteerId === $volunteerId) {
                $windows[] = $membership->window();
            }
        }

        return $windows;
    }

    public function addMembership(TeamMembership $membership): void
    {
        $this->memberships[] = $membership;
    }

    public function approvalWindows(string $roleId, string $volunteerId): array
    {
        $windows = [];
        foreach ($this->approvals as $approval) {
            if ($approval->roleId === $roleId && $approval->volunteerId === $volunteerId) {
                $windows[] = $approval->window();
            }
        }

        return $windows;
    }

    public function addApproval(RoleApproval $approval): void
    {
        $this->approvals[] = $approval;
    }

    public function hasApproval(string $teamId, string $volunteerId, string $approvalId): bool
    {
        foreach ($this->approvals as $approval) {
            if ($approval->id === $approvalId && $approval->volunteerId === $volunteerId && ($this->roles[$approval->roleId] ?? null)?->teamId === $teamId) {
                return true;
            }
        }

        return false;
    }

    public function hasMembership(string $teamId, string $volunteerId, string $membershipId): bool
    {
        foreach ($this->memberships as $membership) {
            if ($membership->id === $membershipId && $membership->volunteerId === $volunteerId && $membership->teamId === $teamId) {
                return true;
            }
        }

        return false;
    }

    public function revokeApproval(string $id, DateTimeImmutable $at): void
    {
        $this->revoked[$id] ??= $at;
    }

    public function revokeMembership(string $id, DateTimeImmutable $at): void
    {
        $this->revoked[$id] ??= $at;
    }
}
