<?php

declare(strict_types=1);

namespace App\Volunteering\Domain\Repository;

use App\Volunteering\Domain\RoleApproval;
use App\Volunteering\Domain\ServingRole;
use App\Volunteering\Domain\Team;
use App\Volunteering\Domain\TeamMembership;
use App\Volunteering\Domain\ValidityWindow;
use DateTimeImmutable;

interface TeamRepository
{
    /** Holds the team row until the transaction ends; the team row serializes its roles, memberships and approvals. */
    public function lock(string $id): ?Team;

    public function insert(Team $team): void;

    /** Saves with the previous version in the WHERE clause; a concurrent change is a conflict. */
    public function save(Team $team): void;

    public function findRole(string $id): ?ServingRole;

    public function addRole(ServingRole $role): void;

    public function saveRole(ServingRole $role): void;

    /** @return list<ValidityWindow> the volunteer's membership windows on the team, at most 1000 */
    public function membershipWindows(string $teamId, string $volunteerId): array;

    public function addMembership(TeamMembership $membership): void;

    /** @return list<ValidityWindow> the volunteer's approval windows for the role, at most 1000 */
    public function approvalWindows(string $roleId, string $volunteerId): array;

    public function addApproval(RoleApproval $approval): void;

    /** True when the approval belongs to the volunteer for a role of the team. */
    public function hasApproval(string $teamId, string $volunteerId, string $approvalId): bool;

    /** True when the membership belongs to the volunteer on the team. */
    public function hasMembership(string $teamId, string $volunteerId, string $membershipId): bool;

    /** Sets `revoked_at` only when it is still null, so a second revocation keeps the first instant. */
    public function revokeApproval(string $id, DateTimeImmutable $at): void;

    /** Sets `revoked_at` only when it is still null, so a second revocation keeps the first instant. */
    public function revokeMembership(string $id, DateTimeImmutable $at): void;
}
