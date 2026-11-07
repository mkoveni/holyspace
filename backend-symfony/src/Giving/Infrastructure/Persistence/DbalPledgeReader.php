<?php

declare(strict_types=1);

namespace App\Giving\Infrastructure\Persistence;

use App\Giving\Application\Contract\HistoryEntry;
use App\Giving\Application\Contract\PledgeAssignmentEntry;
use App\Giving\Application\Contract\PledgeAssignmentView;
use App\Giving\Application\Contract\PledgeCandidate;
use App\Giving\Application\Contract\PledgeDetail;
use App\Giving\Application\Contract\PledgePage;
use App\Giving\Application\Contract\PledgeReader;
use App\Giving\Application\Contract\PledgeSummary;
use App\Giving\Application\Contract\PledgeView;
use App\Giving\Domain\Money;
use App\Giving\Domain\Pledge;
use App\People\Application\Contract\PersonDirectory;
use App\Shared\Domain\CalendarDate;
use App\Shared\Infrastructure\Persistence\Timestamps;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;

/** Pledges as read models. Donor names come from the People directory, never from People tables. */
final readonly class DbalPledgeReader implements PledgeReader
{
    private const string UNAVAILABLE = 'Person unavailable';
    private const array PLEDGE_COLUMNS = ['p.id', 'p.donor_id', 'p.fund_id', 'p.amount_minor', 'p.currency', 'p.starts_on', 'p.ends_on', 'p.status', 'p.version', 'p.cancellation_reason', 'p.created_by', 'p.created_at'];
    private const int HISTORY_LIMIT = 100;

    public function __construct(private Connection $connection, private PersonDirectory $people)
    {
    }

    public function list(?string $donorId, ?string $status, int $limit, int $offset): PledgePage
    {
        $total = $this->matching($donorId, $status)->select('COUNT(*)')->fetchOne();

        return new PledgePage($this->summaries($this->matching($donorId, $status), $limit, $offset), (int) $total);
    }

    public function detail(string $id, int $limit, int $offset): ?PledgeDetail
    {
        $summary = $this->summaries($this->connection->createQueryBuilder()
            ->from('giving_pledges', 'p')
            ->where('p.id = :id')
            ->setParameter('id', $id), 1, 0)[0] ?? null;
        if (null === $summary) {
            return null;
        }

        return new PledgeDetail($summary, $this->assignments($id, $limit, $offset), $this->history($id));
    }

    public function candidates(string $id, int $limit, int $offset): ?array
    {
        $pledge = $this->connection->createQueryBuilder()
            ->select('donor_id', 'fund_id', 'currency', 'starts_on', 'ends_on', 'status')
            ->from('giving_pledges')
            ->where('id = :id')
            ->setParameter('id', $id)
            ->fetchAssociative();
        if (false === $pledge) {
            return null;
        }
        if (Pledge::OPEN !== $pledge['status']) {
            return [];
        }
        // Java subtracts the gift's whole refunded amount, not only the pledge fund's share, as the assign handler does.
        $available = 'a.amount_minor'
            ." - (SELECT COALESCE(SUM(x.amount_minor), 0) FROM giving_pledge_assignments x WHERE x.gift_id = g.id AND x.fund_id = :fund AND x.status = 'ACTIVE')"
            .' - (SELECT COALESCE(SUM(r.amount_minor), 0) FROM giving_payment_refunds r WHERE r.gift_id = g.id)';

        return array_values(array_map(
            static fn (array $row): PledgeCandidate => new PledgeCandidate(
                (string) $row['id'],
                CalendarDate::parse((string) $row['received_on']),
                (int) $row['available'],
                null === $row['external_reference'] ? null : (string) $row['external_reference'],
                (string) $row['method'],
            ),
            $this->connection->createQueryBuilder()
                ->select('g.id', 'g.received_on', "($available) AS available", 'g.external_reference', 'g.method')
                ->from('giving_gifts', 'g')
                ->innerJoin('g', 'giving_allocations', 'a', 'a.gift_id = g.id')
                ->where('g.donor_id = :donor')
                ->andWhere('g.currency = :currency')
                ->andWhere('g.received_on BETWEEN :starts AND :ends')
                ->andWhere('a.fund_id = :fund')
                ->andWhere("($available) > 0")
                ->andWhere('NOT EXISTS (SELECT 1 FROM giving_reversals v WHERE v.gift_id = g.id)')
                ->setParameter('donor', $pledge['donor_id'])
                ->setParameter('currency', $pledge['currency'])
                ->setParameter('starts', $pledge['starts_on'])
                ->setParameter('ends', $pledge['ends_on'])
                ->setParameter('fund', $pledge['fund_id'])
                ->orderBy('g.received_on')
                ->addOrderBy('g.id')
                ->setMaxResults($limit)
                ->setFirstResult($offset)
                ->fetchAllAssociative(),
        ));
    }

    private function matching(?string $donorId, ?string $status): QueryBuilder
    {
        $query = $this->connection->createQueryBuilder()->from('giving_pledges', 'p');
        if (null !== $donorId) {
            $query->andWhere('p.donor_id = :donor')->setParameter('donor', $donorId);
        }
        if (null !== $status) {
            $query->andWhere('p.status = :status')->setParameter('status', $status);
        }

        return $query;
    }

    /**
     * Credit counts active assignments of unreversed gifts, so a reversal reduces progress without touching the assignment.
     *
     * @return list<PledgeSummary>
     */
    private function summaries(QueryBuilder $pledges, int $limit, int $offset): array
    {
        $rows = $pledges
            ->select(...self::PLEDGE_COLUMNS)
            ->addSelect('f.name AS fund_name')
            ->addSelect("(SELECT COALESCE(SUM(x.amount_minor), 0) FROM giving_pledge_assignments x WHERE x.pledge_id = p.id AND x.status = 'ACTIVE'"
                .' AND NOT EXISTS (SELECT 1 FROM giving_reversals r WHERE r.gift_id = x.gift_id)) AS credited')
            ->innerJoin('p', 'giving_funds', 'f', 'f.id = p.fund_id')
            ->orderBy('p.ends_on')
            ->addOrderBy('p.id')
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->fetchAllAssociative();
        $names = [];
        $donors = array_values(array_unique(array_map(static fn (array $row): string => (string) $row['donor_id'], $rows)));
        foreach ([] === $donors ? [] : $this->people->getAll($donors) as $entry) {
            $names[$entry->id] = $entry->name;
        }

        return array_values(array_map(static function (array $row) use ($names): PledgeSummary {
            $pledge = new PledgeView(
                (string) $row['id'],
                (string) $row['donor_id'],
                (string) $row['fund_id'],
                Money::of((int) $row['amount_minor'], (string) $row['currency']),
                CalendarDate::parse((string) $row['starts_on']),
                CalendarDate::parse((string) $row['ends_on']),
                (string) $row['status'],
                (int) $row['version'],
                null === $row['cancellation_reason'] ? null : (string) $row['cancellation_reason'],
                (string) $row['created_by'],
                Timestamps::fromDatabase((string) $row['created_at']),
            );
            $credited = (int) $row['credited'];
            $target = $pledge->target->amountMinor;

            return new PledgeSummary($pledge, $names[$pledge->donorId] ?? self::UNAVAILABLE, (string) $row['fund_name'], $credited, $target - $credited, $credited >= $target);
        }, $rows));
    }

    /** @return list<PledgeAssignmentEntry> */
    private function assignments(string $pledgeId, int $limit, int $offset): array
    {
        return array_values(array_map(
            static fn (array $row): PledgeAssignmentEntry => new PledgeAssignmentEntry(
                new PledgeAssignmentView(
                    (string) $row['id'],
                    (string) $row['pledge_id'],
                    (string) $row['gift_id'],
                    (string) $row['fund_id'],
                    (string) $row['donor_id'],
                    (string) $row['currency'],
                    (int) $row['amount_minor'],
                    (string) $row['status'],
                    (int) $row['version'],
                    null === $row['cancellation_reason'] ? null : (string) $row['cancellation_reason'],
                    null === $row['cancelled_by'] ? null : (string) $row['cancelled_by'],
                    null === $row['cancelled_at'] ? null : Timestamps::fromDatabase((string) $row['cancelled_at']),
                    (string) $row['created_by'],
                    Timestamps::fromDatabase((string) $row['created_at']),
                ),
                CalendarDate::parse((string) $row['received_on']),
                (bool) $row['gift_reversed'],
            ),
            $this->connection->createQueryBuilder()
                ->select('x.*', 'g.received_on', 'EXISTS (SELECT 1 FROM giving_reversals r WHERE r.gift_id = x.gift_id) AS gift_reversed')
                ->from('giving_pledge_assignments', 'x')
                ->innerJoin('x', 'giving_gifts', 'g', 'g.id = x.gift_id')
                ->where('x.pledge_id = :pledge')
                ->setParameter('pledge', $pledgeId)
                ->orderBy('x.created_at', 'DESC')
                ->addOrderBy('x.id')
                ->setMaxResults($limit)
                ->setFirstResult($offset)
                ->fetchAllAssociative(),
        ));
    }

    /** @return list<HistoryEntry> */
    private function history(string $pledgeId): array
    {
        return array_values(array_map(
            static fn (array $row): HistoryEntry => new HistoryEntry(
                (string) $row['action'],
                (string) $row['reason'],
                (string) $row['actor_id'],
                Timestamps::fromDatabase((string) $row['occurred_at']),
            ),
            $this->connection->createQueryBuilder()
                ->select('action', 'reason', 'actor_id', 'occurred_at')
                ->from('giving_history')
                ->where("target_type = 'PLEDGE'")
                ->andWhere('target_id = :pledge')
                ->setParameter('pledge', $pledgeId)
                ->orderBy('occurred_at', 'DESC')
                ->addOrderBy('id')
                ->setMaxResults(self::HISTORY_LIMIT)
                ->fetchAllAssociative(),
        ));
    }
}
