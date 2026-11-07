<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Participation\Infrastructure\Persistence;

use App\ChildrenMinistry\Participation\Application\Contract\ProgrammeRosterReader;
use App\ChildrenMinistry\Participation\Application\Placement;
use App\ChildrenMinistry\Participation\Application\RosterPage;
use App\Shared\Domain\CalendarDate;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;

final readonly class DbalProgrammeRosterStore implements ProgrammeRosterReader
{
    public function __construct(private Connection $connection)
    {
    }

    public function list(string $programmeId, ?array $childIds, string $status, CalendarDate $on, int $limit, int $offset): RosterPage
    {
        if ([] === $childIds) {
            return new RosterPage([], 0);
        }
        $where = fn (QueryBuilder $query): QueryBuilder => $this->filter($query, $programmeId, $childIds, $status, $on);

        $total = (int) $where($this->connection->createQueryBuilder()->select('count(*)')->from('children_enrolments'))->fetchOne();
        $rows = $where($this->connection->createQueryBuilder()->select('*')->from('children_enrolments'))
            ->orderBy('created_at', 'DESC')
            ->addOrderBy('id')
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->fetchAllAssociative();

        return new RosterPage(array_values(array_map(DbalParticipationStore::enrolmentView(...), $rows)), $total);
    }

    public function placements(array $enrolmentIds, CalendarDate $on): array
    {
        if ([] === $enrolmentIds) {
            return [];
        }
        $rows = $this->connection->createQueryBuilder()
            ->select('a.enrolment_id', 'c.id', 'c.name')
            ->from('children_class_assignments', 'a')
            ->join('a', 'children_classes', 'c', 'c.id = a.class_id')
            ->where('a.enrolment_id IN (:enrolments)')
            ->andWhere('a.ended_at IS NULL')
            ->andWhere('a.valid_from <= :on')
            ->andWhere('a.valid_until > :on')
            ->setParameter('enrolments', $enrolmentIds, ArrayParameterType::STRING)
            ->setParameter('on', $on->iso())
            ->orderBy('c.name')
            ->addOrderBy('c.id')
            ->fetchAllAssociative();

        $grouped = [];
        foreach ($rows as $row) {
            $grouped[(string) $row['enrolment_id']][] = new Placement((string) $row['id'], (string) $row['name']);
        }

        return $grouped;
    }

    /** @param list<string>|null $childIds */
    private function filter(QueryBuilder $query, string $programmeId, ?array $childIds, string $status, CalendarDate $on): QueryBuilder
    {
        $query->where('programme_id = :programme')->setParameter('programme', $programmeId);
        if (null !== $childIds) {
            $query->andWhere('child_id IN (:children)')->setParameter('children', $childIds, ArrayParameterType::STRING);
        }
        $condition = match ($status) {
            'ACTIVE' => 'ended_at IS NULL AND valid_from <= :on AND valid_until > :on',
            'UPCOMING' => 'ended_at IS NULL AND valid_from > :on',
            'EXPIRED' => 'ended_at IS NULL AND valid_until <= :on',
            'ENDED' => 'ended_at IS NOT NULL',
            default => null,
        };
        if (null !== $condition) {
            $query->andWhere($condition)->setParameter('on', $on->iso());
        }

        return $query;
    }
}
