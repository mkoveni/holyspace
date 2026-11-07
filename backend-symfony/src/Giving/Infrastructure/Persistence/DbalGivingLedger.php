<?php

declare(strict_types=1);

namespace App\Giving\Infrastructure\Persistence;

use App\Giving\Application\Contract\GiftFilter;
use App\Giving\Application\Contract\GivingLedgerReader;
use App\Giving\Application\Contract\LedgerLine;
use App\Giving\Domain\GivingException;
use App\People\Application\Contract\PersonDirectory;
use App\Shared\Domain\CalendarDate;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Query\UnionType;

/**
 * Allocation-level ledger (Java JooqGivingLedger): one line per gift allocation by received date, one negated line per
 * allocation of a full reversal by reversal date, and one negated line per provider refund by refund date.
 */
final readonly class DbalGivingLedger implements GivingLedgerReader
{
    public function __construct(private Connection $connection, private PersonDirectory $people)
    {
    }

    public function lines(GiftFilter $filter, int $maximum): array
    {
        $gifts = LedgerConditions::restrict($this->part(
            "'GIFT'",
            'g.received_on',
            'a.amount_minor',
            'g.external_reference',
            "''",
        ), $filter, 'g.received_on');
        $reversals = LedgerConditions::restrict($this->part(
            "'REVERSAL'",
            'r.reversed_on',
            '-a.amount_minor',
            'g.external_reference',
            'r.reason',
        )->innerJoin('g', 'giving_reversals', 'r', 'r.gift_id = g.id'), $filter, 'r.reversed_on');
        $refunds = LedgerConditions::restrict($this->connection->createQueryBuilder()
            ->select(
                'g.id AS gift_id',
                "'REFUND' AS kind",
                'p.refunded_on AS line_date',
                'g.donor_id',
                'f.id AS fund_id',
                'f.name AS fund_name',
                'g.currency',
                '-p.amount_minor AS amount_minor',
                'g.method',
                'p.reference',
                "'Provider-completed refund' AS reason",
            )
            ->from('giving_gifts', 'g')
            ->innerJoin('g', 'giving_payment_refunds', 'p', 'p.gift_id = g.id')
            ->innerJoin('g', 'giving_allocations', 'a', 'a.gift_id = g.id AND a.fund_id = p.fund_id')
            ->innerJoin('p', 'giving_funds', 'f', 'f.id = p.fund_id'), $filter, 'p.refunded_on');

        $rows = LedgerConditions::bind($this->connection->createQueryBuilder()
            ->union($gifts)
            ->addUnion($reversals, UnionType::ALL)
            ->addUnion($refunds, UnionType::ALL)
            ->orderBy('line_date')
            ->addOrderBy('gift_id')
            ->addOrderBy('kind')
            ->addOrderBy('fund_id')
            ->setMaxResults($maximum + 1), $filter)
            ->fetchAllAssociative();
        if (\count($rows) > $maximum) {
            throw GivingException::conflict("This report exceeds $maximum allocation lines. Narrow the date range or choose a donor or fund.");
        }

        $names = [];
        $donors = array_values(array_unique(array_filter(array_map(
            static fn (array $row): ?string => null === $row['donor_id'] ? null : (string) $row['donor_id'],
            $rows,
        ), static fn (?string $id): bool => null !== $id)));
        foreach ([] === $donors ? [] : $this->people->getAll($donors) as $entry) {
            $names[$entry->id] = $entry->name;
        }

        return array_values(array_map(static function (array $row) use ($names): LedgerLine {
            $donor = null === $row['donor_id'] ? null : (string) $row['donor_id'];

            return new LedgerLine(
                (string) $row['gift_id'],
                (string) $row['kind'],
                CalendarDate::parse((string) $row['line_date']),
                $donor,
                null === $donor ? 'Anonymous' : ($names[$donor] ?? 'Person unavailable'),
                (string) $row['fund_id'],
                (string) $row['fund_name'],
                (string) $row['currency'],
                (int) $row['amount_minor'],
                (string) $row['method'],
                null === $row['reference'] ? null : (string) $row['reference'],
                (string) $row['reason'],
            );
        }, $rows));
    }

    /** A gift-and-allocation part of the union with the given kind, date, signed amount, reference and reason. */
    private function part(string $kind, string $date, string $amount, string $reference, string $reason): QueryBuilder
    {
        return $this->connection->createQueryBuilder()
            ->select(
                'g.id AS gift_id',
                "$kind AS kind",
                "$date AS line_date",
                'g.donor_id',
                'f.id AS fund_id',
                'f.name AS fund_name',
                'g.currency',
                "$amount AS amount_minor",
                'g.method',
                "$reference AS reference",
                "$reason AS reason",
            )
            ->from('giving_gifts', 'g')
            ->innerJoin('g', 'giving_allocations', 'a', 'a.gift_id = g.id')
            ->innerJoin('a', 'giving_funds', 'f', 'f.id = a.fund_id');
    }
}
