<?php

declare(strict_types=1);

namespace App\Volunteering\Infrastructure\Persistence;

use App\Volunteering\Application\Contract\RoleView;
use App\Volunteering\Application\Contract\TeamReader;
use App\Volunteering\Application\Contract\TeamView;
use App\Volunteering\Domain\ValidityWindow;
use Doctrine\DBAL\Connection;

final readonly class DbalTeamReader implements TeamReader
{
    private const WINDOW_LIMIT = 1000;

    public function __construct(private Connection $connection)
    {
    }

    public function team(string $id): ?TeamView
    {
        $row = $this->connection->createQueryBuilder()
            ->select('id', 'active')
            ->from('serving_teams')
            ->where('id = :id')
            ->setParameter('id', $id)
            ->fetchAssociative();

        return false === $row ? null : new TeamView((string) $row['id'], (bool) $row['active']);
    }

    public function role(string $id): ?RoleView
    {
        $row = $this->connection->createQueryBuilder()
            ->select('id', 'team_id', 'children_role', 'active')
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
            ->fetchFirstColumn();

        return new RoleView((string) $row['id'], (string) $row['team_id'], (string) $row['children_role'], (bool) $row['active'], array_values(array_map(strval(...), $required)));
    }

    public function memberships(string $teamId, string $volunteerId): array
    {
        return $this->windows('serving_memberships', 'team_id', $teamId, $volunteerId);
    }

    public function approvals(string $roleId, string $volunteerId): array
    {
        return $this->windows('serving_role_approvals', 'role_id', $roleId, $volunteerId);
    }

    /** @return list<ValidityWindow> */
    private function windows(string $table, string $scopeColumn, string $scopeId, string $volunteerId): array
    {
        $rows = $this->connection->createQueryBuilder()
            ->select('valid_from', 'valid_until', 'revoked_at')
            ->from($table)
            ->where($scopeColumn.' = :scope')
            ->andWhere('volunteer_id = :volunteer')
            ->setParameter('scope', $scopeId)
            ->setParameter('volunteer', $volunteerId)
            ->orderBy('valid_from', 'DESC')
            ->setMaxResults(self::WINDOW_LIMIT)
            ->fetchAllAssociative();

        return array_values(array_map(ValidityWindows::fromRow(...), $rows));
    }
}
