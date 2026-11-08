<?php

declare(strict_types=1);

namespace App\Volunteering\Infrastructure\Persistence;

use App\Volunteering\Application\Contract\LinkedActivities;
use App\Volunteering\Application\Contract\ServingActivity;
use App\Volunteering\Domain\VolunteerException;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;

/** Java `JooqRosterStore.volunteerActivities/teamActivities`. */
final readonly class DbalLinkedActivities implements LinkedActivities
{
    private const int LIMIT = 1000;

    public function __construct(private Connection $connection)
    {
    }

    public function ofVolunteers(array $volunteerIds): array
    {
        if ([] === $volunteerIds) {
            return [];
        }

        return $this->activities($this->connection->createQueryBuilder()
            ->select('DISTINCT s.source_kind', 's.source_id')
            ->from('serving_shifts', 's')
            ->innerJoin('s', 'serving_assignments', 'a', 'a.shift_id = s.id')
            ->where('a.volunteer_id IN (:volunteers)')
            ->andWhere('s.source_id IS NOT NULL')
            ->andWhere("a.status <> 'CANCELLED'")
            ->setParameter('volunteers', $volunteerIds, ArrayParameterType::STRING));
    }

    public function ofTeam(string $teamId): array
    {
        return $this->activities($this->connection->createQueryBuilder()
            ->select('DISTINCT s.source_kind', 's.source_id')
            ->from('serving_shifts', 's')
            ->where('s.team_id = :team')
            ->andWhere('s.source_id IS NOT NULL')
            ->setParameter('team', $teamId));
    }

    /** @return list<ServingActivity> */
    private function activities(QueryBuilder $query): array
    {
        $rows = $query->setMaxResults(self::LIMIT + 1)->fetchAllAssociative();
        if (\count($rows) > self::LIMIT) {
            throw VolunteerException::conflict('Too many linked activities. Archive completed planning before changing eligibility.');
        }

        return array_values(array_map(static fn (array $row): ServingActivity => new ServingActivity((string) $row['source_kind'], (string) $row['source_id']), $rows));
    }
}
