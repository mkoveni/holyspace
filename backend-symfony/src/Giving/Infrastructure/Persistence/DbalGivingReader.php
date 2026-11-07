<?php

declare(strict_types=1);

namespace App\Giving\Infrastructure\Persistence;

use App\Giving\Application\Contract\FundPage;
use App\Giving\Application\Contract\FundView;
use App\Giving\Application\Contract\GiftDetail;
use App\Giving\Application\Contract\GiftFilter;
use App\Giving\Application\Contract\GiftHistoryEntry;
use App\Giving\Application\Contract\GiftPage;
use App\Giving\Application\Contract\GiftSummary;
use App\Giving\Application\Contract\GivingReader;
use App\Giving\Application\Contract\ReversalView;
use App\Giving\Domain\Allocation;
use App\Giving\Domain\Money;
use App\People\Application\Contract\PersonDirectory;
use App\Shared\Domain\CalendarDate;
use App\Shared\Infrastructure\Persistence\LikePattern;
use App\Shared\Infrastructure\Persistence\Timestamps;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;

/** Funds and gifts as read models. Donor names come from the People directory, never from People tables. */
final readonly class DbalGivingReader implements GivingReader
{
    private const string ANONYMOUS = 'Anonymous';
    private const string UNAVAILABLE = 'Person unavailable';

    public function __construct(private Connection $connection, private PersonDirectory $people)
    {
    }

    public function funds(bool $archived, string $search, int $limit, int $offset): FundPage
    {
        $rows = $this->matchingFunds($archived, $search)
            ->select('id', 'name', 'description', 'active', 'version')
            ->orderBy('name')
            ->addOrderBy('id')
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->fetchAllAssociative();
        $total = $this->matchingFunds($archived, $search)->select('COUNT(*)')->fetchOne();

        return new FundPage(array_values(array_map(
            static fn (array $row): FundView => new FundView(
                (string) $row['id'],
                (string) $row['name'],
                (string) $row['description'],
                (bool) $row['active'],
                (int) $row['version'],
            ),
            $rows,
        )), (int) $total);
    }

    public function gifts(GiftFilter $filter): GiftPage
    {
        $rows = $this->matchingGifts($filter)
            ->select('g.id', 'g.donor_id', 'g.amount_minor', 'g.currency', 'g.received_on', 'g.method', 'g.external_reference')
            ->orderBy('g.received_on', 'DESC')
            ->addOrderBy('g.id', 'DESC')
            ->setMaxResults($filter->limit)
            ->setFirstResult($filter->offset)
            ->fetchAllAssociative();
        $total = $this->matchingGifts($filter)->select('COUNT(*)')->fetchOne();
        $ids = array_values(array_map(static fn (array $row): string => (string) $row['id'], $rows));
        $reversed = array_flip($this->giftIdsIn('giving_reversals', $ids));
        $reconciled = array_flip($this->giftIdsIn('giving_reconciliation_items', $ids));
        $refunded = $this->refunds($ids);
        $names = $this->names(array_values(array_unique(array_filter(array_map(
            static fn (array $row): ?string => null === $row['donor_id'] ? null : (string) $row['donor_id'],
            $rows,
        ), static fn (?string $id): bool => null !== $id))));

        return new GiftPage(array_values(array_map(static function (array $row) use ($reversed, $reconciled, $refunded, $names): GiftSummary {
            $id = (string) $row['id'];
            $donor = null === $row['donor_id'] ? null : (string) $row['donor_id'];

            return new GiftSummary(
                $id,
                $donor,
                null === $donor ? self::ANONYMOUS : ($names[$donor] ?? self::UNAVAILABLE),
                (int) $row['amount_minor'],
                (string) $row['currency'],
                CalendarDate::parse((string) $row['received_on']),
                (string) $row['method'],
                null === $row['external_reference'] ? null : (string) $row['external_reference'],
                isset($reversed[$id]),
                isset($reconciled[$id]),
                $refunded[$id] ?? 0,
            );
        }, $rows)), (int) $total);
    }

    public function gift(string $id): ?GiftDetail
    {
        $row = $this->connection->createQueryBuilder()
            ->select('id', 'donor_id', 'amount_minor', 'currency', 'received_on', 'method', 'external_reference', 'created_by', 'created_at')
            ->from('giving_gifts')
            ->where('id = :id')
            ->setParameter('id', $id)
            ->fetchAssociative();
        if (false === $row) {
            return null;
        }
        $donor = null === $row['donor_id'] ? null : (string) $row['donor_id'];
        $allocations = array_values(array_map(
            static fn (array $a): Allocation => new Allocation((string) $a['fund_id'], (int) $a['amount_minor']),
            $this->connection->createQueryBuilder()
                ->select('fund_id', 'amount_minor')
                ->from('giving_allocations')
                ->where('gift_id = :id')
                ->setParameter('id', $id)
                ->orderBy('fund_id')
                ->fetchAllAssociative(),
        ));
        $fundNames = [];
        foreach ($this->connection->createQueryBuilder()
            ->select('id', 'name')
            ->from('giving_funds')
            ->where('id IN (:ids)')
            ->setParameter('ids', array_map(static fn (Allocation $a): string => $a->fundId, $allocations), ArrayParameterType::STRING)
            ->fetchAllAssociative() as $fund) {
            $fundNames[(string) $fund['id']] = (string) $fund['name'];
        }

        return new GiftDetail(
            (string) $row['id'],
            $donor,
            Money::of((int) $row['amount_minor'], (string) $row['currency']),
            CalendarDate::parse((string) $row['received_on']),
            (string) $row['method'],
            null === $row['external_reference'] ? null : (string) $row['external_reference'],
            $allocations,
            (string) $row['created_by'],
            Timestamps::fromDatabase((string) $row['created_at']),
            null === $donor ? self::ANONYMOUS : ($this->people->get($donor)->name ?? self::UNAVAILABLE),
            $fundNames,
            $this->reversal($id),
            $this->history($id),
            $this->refunds([$id])[$id] ?? 0,
        );
    }

    private function matchingFunds(bool $archived, string $search): QueryBuilder
    {
        $query = $this->connection->createQueryBuilder()->from('giving_funds');
        if (!$archived) {
            $query->andWhere('active');
        }
        if ('' !== $search) {
            $query->andWhere('name ILIKE :pattern')->setParameter('pattern', LikePattern::contains($search));
        }

        return $query;
    }

    private function matchingGifts(GiftFilter $filter): QueryBuilder
    {
        $query = $this->connection->createQueryBuilder()
            ->from('giving_gifts', 'g')
            ->where('g.received_on BETWEEN :from AND :to')
            ->setParameter('from', $filter->from?->iso())
            ->setParameter('to', $filter->to?->iso());
        if (null !== $filter->currency) {
            $query->andWhere('g.currency = :currency')->setParameter('currency', $filter->currency);
        }
        if (null !== $filter->donorId) {
            $query->andWhere('g.donor_id = :donor')->setParameter('donor', $filter->donorId);
        }
        if (null !== $filter->fundId) {
            $query->andWhere('EXISTS (SELECT 1 FROM giving_allocations a WHERE a.gift_id = g.id AND a.fund_id = :fund)')
                ->setParameter('fund', $filter->fundId);
        }

        return $query;
    }

    /**
     * @param list<string> $ids
     *
     * @return list<string>
     */
    private function giftIdsIn(string $table, array $ids): array
    {
        if ([] === $ids) {
            return [];
        }

        return array_values(array_map('strval', $this->connection->createQueryBuilder()
            ->select('DISTINCT gift_id')
            ->from($table)
            ->where('gift_id IN (:ids)')
            ->setParameter('ids', $ids, ArrayParameterType::STRING)
            ->fetchFirstColumn()));
    }

    /**
     * Refunded minor units per gift, from Payments refund evidence.
     *
     * @param list<string> $ids
     *
     * @return array<string, int>
     */
    private function refunds(array $ids): array
    {
        if ([] === $ids) {
            return [];
        }
        $refunds = [];
        foreach ($this->connection->createQueryBuilder()
            ->select('gift_id', 'SUM(amount_minor) AS refunded')
            ->from('giving_payment_refunds')
            ->where('gift_id IN (:ids)')
            ->setParameter('ids', $ids, ArrayParameterType::STRING)
            ->groupBy('gift_id')
            ->fetchAllAssociative() as $row) {
            $refunds[(string) $row['gift_id']] = (int) $row['refunded'];
        }

        return $refunds;
    }

    /**
     * @param list<string> $ids
     *
     * @return array<string, string>
     */
    private function names(array $ids): array
    {
        $names = [];
        foreach ([] === $ids ? [] : $this->people->getAll($ids) as $entry) {
            $names[$entry->id] = $entry->name;
        }

        return $names;
    }

    private function reversal(string $giftId): ?ReversalView
    {
        $row = $this->connection->createQueryBuilder()
            ->select('id', 'gift_id', 'reversed_on', 'reason', 'created_by', 'created_at')
            ->from('giving_reversals')
            ->where('gift_id = :gift')
            ->setParameter('gift', $giftId)
            ->fetchAssociative();

        return false === $row ? null : new ReversalView(
            (string) $row['id'],
            (string) $row['gift_id'],
            CalendarDate::parse((string) $row['reversed_on']),
            (string) $row['reason'],
            (string) $row['created_by'],
            Timestamps::fromDatabase((string) $row['created_at']),
        );
    }

    /** @return list<GiftHistoryEntry> */
    private function history(string $giftId): array
    {
        return array_values(array_map(
            static fn (array $row): GiftHistoryEntry => new GiftHistoryEntry(
                (string) $row['action'],
                (string) $row['reason'],
                (string) $row['actor_id'],
                Timestamps::fromDatabase((string) $row['occurred_at']),
            ),
            $this->connection->createQueryBuilder()
                ->select('action', 'reason', 'actor_id', 'occurred_at')
                ->from('giving_history')
                ->where('target_id = :gift')
                ->andWhere("target_type = 'GIFT'")
                ->setParameter('gift', $giftId)
                ->orderBy('occurred_at')
                ->addOrderBy('id')
                ->fetchAllAssociative(),
        ));
    }
}
