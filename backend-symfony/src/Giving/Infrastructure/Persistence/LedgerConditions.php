<?php

declare(strict_types=1);

namespace App\Giving\Infrastructure\Persistence;

use App\Giving\Application\Contract\GiftFilter;
use Doctrine\DBAL\Query\QueryBuilder;

/**
 * Filter conditions shared by the parts of a ledger UNION (gifts by received date, reversals and refunds by their own
 * date). Each part references the same named placeholders; `bind` sets them once on the outer query. Parts alias the
 * gift `g` and the allocation `a`, so a fund filter keeps only that fund's allocation.
 */
final class LedgerConditions
{
    private function __construct()
    {
    }

    public static function restrict(QueryBuilder $part, GiftFilter $filter, string $dateColumn): QueryBuilder
    {
        $part->where("$dateColumn BETWEEN :from AND :to");
        if (null !== $filter->currency) {
            $part->andWhere('g.currency = :currency');
        }
        if (null !== $filter->donorId) {
            $part->andWhere('g.donor_id = :donor');
        }
        if (null !== $filter->fundId) {
            $part->andWhere('a.fund_id = :fund');
        }

        return $part;
    }

    public static function bind(QueryBuilder $query, GiftFilter $filter): QueryBuilder
    {
        $query->setParameter('from', $filter->from?->iso())->setParameter('to', $filter->to?->iso());
        if (null !== $filter->currency) {
            $query->setParameter('currency', $filter->currency);
        }
        if (null !== $filter->donorId) {
            $query->setParameter('donor', $filter->donorId);
        }
        if (null !== $filter->fundId) {
            $query->setParameter('fund', $filter->fundId);
        }

        return $query;
    }
}
