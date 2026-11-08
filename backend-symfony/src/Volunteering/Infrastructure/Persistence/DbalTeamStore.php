<?php

declare(strict_types=1);

namespace App\Volunteering\Infrastructure\Persistence;

use App\Shared\Infrastructure\Persistence\Timestamps;
use App\Volunteering\Application\Contract\ServingPeriod;
use App\Volunteering\Application\Contract\ServingRoleRecord;
use App\Volunteering\Application\Contract\TeamDirectory;
use App\Volunteering\Application\Contract\TeamRecord;
use App\Volunteering\Domain\Repository\TeamRepository;
use App\Volunteering\Domain\RoleApproval;
use App\Volunteering\Domain\ServingRole;
use App\Volunteering\Domain\Team;
use App\Volunteering\Domain\TeamMembership;
use App\Volunteering\Domain\VolunteerException;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\DriverException;
use Doctrine\DBAL\ParameterType;

/** Java `JooqTeamStore`: teams, roles and their requirements, memberships and approvals (V14). */
final readonly class DbalTeamStore implements TeamRepository, TeamDirectory
{
    private const int ROLE_LIMIT = 100;
    private const int PERIOD_LIMIT = 1000;

    public function __construct(private Connection $connection)
    {
    }

    public function lock(string $id): ?Team
    {
        $row = $this->connection->createQueryBuilder()
            ->select('id', 'name', 'active', 'version')
            ->from('serving_teams')
            ->where('id = :id')
            ->setParameter('id', $id)
            ->forUpdate()
            ->fetchAssociative();

        return false === $row ? null : Team::reconstitute((string) $row['id'], (string) $row['name'], (bool) $row['active'], (int) $row['version']);
    }

    public function insert(Team $team): void
    {
        $this->guard(fn (): int|string => $this->connection->insert('serving_teams', [
            'id' => $team->id,
            'name' => $team->name,
            'active' => $team->active,
            'version' => $team->version,
        ], ['active' => ParameterType::BOOLEAN]));
    }

    public function save(Team $team): void
    {
        $rows = $this->guard(fn (): int|string => $this->connection->update('serving_teams', [
            'name' => $team->name,
            'active' => $team->active,
            'version' => $team->version,
        ], ['id' => $team->id, 'version' => $team->version - 1], ['active' => ParameterType::BOOLEAN, 'version' => ParameterType::INTEGER]));
        if (1 !== $rows) {
            throw VolunteerException::conflict('This record changed. Refresh before saving.');
        }
    }

    public function findRole(string $id): ?ServingRole
    {
        $row = $this->roleRow($id);

        return null === $row ? null : ServingRole::reconstitute($row->id, $row->teamId, $row->name, $row->childrenRole, $row->active, $row->requiredCredentials);
    }

    public function addRole(ServingRole $role): void
    {
        $this->guard(fn (): int|string => $this->connection->insert('serving_roles', [
            'id' => $role->id,
            'team_id' => $role->teamId,
            'name' => $role->name,
            'children_role' => $role->childrenRole,
            'active' => $role->active,
        ], ['active' => ParameterType::BOOLEAN]));
        $this->insertRequirements($role);
    }

    public function saveRole(ServingRole $role): void
    {
        $this->guard(fn (): int|string => $this->connection->update('serving_roles', [
            'name' => $role->name,
            'children_role' => $role->childrenRole,
            'active' => $role->active,
        ], ['id' => $role->id, 'team_id' => $role->teamId], ['active' => ParameterType::BOOLEAN]));
        $this->connection->delete('serving_role_requirements', ['role_id' => $role->id]);
        $this->insertRequirements($role);
    }

    public function membershipWindows(string $teamId, string $volunteerId): array
    {
        return array_map(ValidityWindows::fromRow(...), $this->periodRows('serving_memberships', 'team_id', $teamId, $volunteerId));
    }

    public function addMembership(TeamMembership $membership): void
    {
        $this->guard(fn (): int|string => $this->connection->insert('serving_memberships', [
            'id' => $membership->id,
            'team_id' => $membership->teamId,
            'volunteer_id' => $membership->volunteerId,
            'valid_from' => Timestamps::toDatabase($membership->validFrom),
            'valid_until' => Timestamps::toDatabase($membership->validUntil),
            'revoked_at' => null === $membership->revokedAt ? null : Timestamps::toDatabase($membership->revokedAt),
        ]));
    }

    public function approvalWindows(string $roleId, string $volunteerId): array
    {
        return array_map(ValidityWindows::fromRow(...), $this->periodRows('serving_role_approvals', 'role_id', $roleId, $volunteerId));
    }

    public function addApproval(RoleApproval $approval): void
    {
        $this->guard(fn (): int|string => $this->connection->insert('serving_role_approvals', [
            'id' => $approval->id,
            'role_id' => $approval->roleId,
            'volunteer_id' => $approval->volunteerId,
            'valid_from' => Timestamps::toDatabase($approval->validFrom),
            'valid_until' => Timestamps::toDatabase($approval->validUntil),
            'revoked_at' => null === $approval->revokedAt ? null : Timestamps::toDatabase($approval->revokedAt),
        ]));
    }

    public function hasApproval(string $teamId, string $volunteerId, string $approvalId): bool
    {
        return false !== $this->connection->createQueryBuilder()
            ->select('1')
            ->from('serving_role_approvals', 'a')
            ->innerJoin('a', 'serving_roles', 'r', 'r.id = a.role_id')
            ->where('a.id = :id')
            ->andWhere('a.volunteer_id = :volunteer')
            ->andWhere('r.team_id = :team')
            ->setParameter('id', $approvalId)
            ->setParameter('volunteer', $volunteerId)
            ->setParameter('team', $teamId)
            ->fetchOne();
    }

    public function hasMembership(string $teamId, string $volunteerId, string $membershipId): bool
    {
        return false !== $this->connection->createQueryBuilder()
            ->select('1')
            ->from('serving_memberships')
            ->where('id = :id')
            ->andWhere('volunteer_id = :volunteer')
            ->andWhere('team_id = :team')
            ->setParameter('id', $membershipId)
            ->setParameter('volunteer', $volunteerId)
            ->setParameter('team', $teamId)
            ->fetchOne();
    }

    public function revokeApproval(string $id, DateTimeImmutable $at): void
    {
        $this->revoke('serving_role_approvals', $id, $at);
    }

    public function revokeMembership(string $id, DateTimeImmutable $at): void
    {
        $this->revoke('serving_memberships', $id, $at);
    }

    public function teams(int $limit, int $offset): array
    {
        $rows = $this->connection->createQueryBuilder()
            ->select('id', 'name', 'active', 'version')
            ->from('serving_teams')
            ->orderBy('name')
            ->addOrderBy('id')
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->fetchAllAssociative();

        return array_values(array_map(self::teamRecord(...), $rows));
    }

    public function teamCount(): int
    {
        return (int) $this->connection->createQueryBuilder()->select('COUNT(*)')->from('serving_teams')->fetchOne();
    }

    public function team(string $id): ?TeamRecord
    {
        $row = $this->connection->createQueryBuilder()
            ->select('id', 'name', 'active', 'version')
            ->from('serving_teams')
            ->where('id = :id')
            ->setParameter('id', $id)
            ->fetchAssociative();

        return false === $row ? null : self::teamRecord($row);
    }

    public function role(string $id): ?ServingRoleRecord
    {
        return $this->roleRow($id);
    }

    public function roles(string $teamId): array
    {
        $ids = $this->connection->createQueryBuilder()
            ->select('id')
            ->from('serving_roles')
            ->where('team_id = :team')
            ->setParameter('team', $teamId)
            ->orderBy('name')
            ->addOrderBy('id')
            ->setMaxResults(self::ROLE_LIMIT)
            ->fetchFirstColumn();
        $roles = [];
        foreach ($ids as $id) {
            $role = $this->roleRow((string) $id);
            if (null !== $role) {
                $roles[] = $role;
            }
        }

        return $roles;
    }

    public function memberships(string $teamId): array
    {
        return array_map(static fn (array $row): ServingPeriod => self::period($row, 'team_id'), $this->periodRows('serving_memberships', 'team_id', $teamId, null));
    }

    public function approvals(string $roleId): array
    {
        return array_map(static fn (array $row): ServingPeriod => self::period($row, 'role_id'), $this->periodRows('serving_role_approvals', 'role_id', $roleId, null));
    }

    private function roleRow(string $id): ?ServingRoleRecord
    {
        $row = $this->connection->createQueryBuilder()
            ->select('id', 'team_id', 'name', 'children_role', 'active')
            ->from('serving_roles')
            ->where('id = :id')
            ->setParameter('id', $id)
            ->fetchAssociative();
        if (false === $row) {
            return null;
        }
        $required = $this->connection->createQueryBuilder()
            ->select('type_id')
            ->from('serving_role_requirements')
            ->where('role_id = :id')
            ->setParameter('id', $id)
            ->orderBy('type_id')
            ->fetchFirstColumn();

        return new ServingRoleRecord((string) $row['id'], (string) $row['team_id'], (string) $row['name'], (string) $row['children_role'], (bool) $row['active'], array_values(array_map(strval(...), $required)));
    }

    private function insertRequirements(ServingRole $role): void
    {
        foreach ($role->requiredCredentials as $type) {
            $this->guard(fn (): int|string => $this->connection->insert('serving_role_requirements', ['role_id' => $role->id, 'type_id' => $type]));
        }
    }

    private function revoke(string $table, string $id, DateTimeImmutable $at): void
    {
        $this->connection->createQueryBuilder()
            ->update($table)
            ->set('revoked_at', ':at')
            ->where('id = :id')
            ->andWhere('revoked_at IS NULL')
            ->setParameter('at', Timestamps::toDatabase($at))
            ->setParameter('id', $id)
            ->executeStatement();
    }

    /** @return list<array<string, mixed>> newest first */
    private function periodRows(string $table, string $scopeColumn, string $scopeId, ?string $volunteerId): array
    {
        $query = $this->connection->createQueryBuilder()
            ->select('id', $scopeColumn, 'volunteer_id', 'valid_from', 'valid_until', 'revoked_at')
            ->from($table)
            ->where($scopeColumn.' = :scope')
            ->setParameter('scope', $scopeId)
            ->orderBy('valid_from', 'DESC')
            ->setMaxResults(self::PERIOD_LIMIT);
        if (null !== $volunteerId) {
            $query->andWhere('volunteer_id = :volunteer')->setParameter('volunteer', $volunteerId);
        }

        return array_values($query->fetchAllAssociative());
    }

    /** @param array<string, mixed> $row */
    private static function teamRecord(array $row): TeamRecord
    {
        return new TeamRecord((string) $row['id'], (string) $row['name'], (bool) $row['active'], (int) $row['version']);
    }

    /** @param array<string, mixed> $row */
    private static function period(array $row, string $scopeColumn): ServingPeriod
    {
        return new ServingPeriod(
            (string) $row['id'],
            (string) $row[$scopeColumn],
            (string) $row['volunteer_id'],
            Timestamps::fromDatabase((string) $row['valid_from']),
            Timestamps::fromDatabase((string) $row['valid_until']),
            null === $row['revoked_at'] ? null : Timestamps::fromDatabase((string) $row['revoked_at']),
        );
    }

    /**
     * Java maps `DuplicateKeyException` to one 409 (for example a second role with the same name on a team).
     *
     * @param callable(): (int|string) $write
     */
    private function guard(callable $write): int
    {
        try {
            return (int) $write();
        } catch (DriverException $error) {
            if ('23505' === $error->getSQLState()) {
                throw VolunteerException::clash();
            }
            throw $error;
        }
    }
}
