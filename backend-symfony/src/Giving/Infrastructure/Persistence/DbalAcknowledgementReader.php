<?php

declare(strict_types=1);

namespace App\Giving\Infrastructure\Persistence;

use App\Giving\Application\Contract\AcknowledgementReader;
use App\Giving\Application\Contract\AcknowledgementView;
use App\Giving\Domain\AcknowledgementLine;
use App\Shared\Domain\CalendarDate;
use Doctrine\DBAL\Connection;

/** Acknowledgements as read models, with the gift's current reversal or refund status. */
final readonly class DbalAcknowledgementReader implements AcknowledgementReader
{
    private const string REFUND_REASON = 'Provider-completed refund';

    public function __construct(private Connection $connection)
    {
    }

    public function get(string $id): ?AcknowledgementView
    {
        return $this->view('a.id', $id);
    }

    public function forGift(string $giftId): ?AcknowledgementView
    {
        return $this->view('a.gift_id', $giftId);
    }

    /** @param 'a.id'|'a.gift_id' $column */
    private function view(string $column, string $value): ?AcknowledgementView
    {
        $row = $this->connection->createQueryBuilder()
            ->select(
                'a.id',
                'a.gift_id',
                'a.number',
                'a.donor_name',
                'a.issued_on',
                'g.received_on',
                'g.amount_minor',
                'g.currency',
                'g.method',
                'g.external_reference',
                'r.reversed_on',
                'r.reason AS reversal_reason',
                '(SELECT MIN(f.refunded_on) FROM giving_payment_refunds f WHERE f.gift_id = g.id) AS refunded_on',
            )
            ->from('giving_acknowledgements', 'a')
            ->innerJoin('a', 'giving_gifts', 'g', 'g.id = a.gift_id')
            ->leftJoin('g', 'giving_reversals', 'r', 'r.gift_id = g.id')
            ->where($column.' = :value')
            ->setParameter('value', $value)
            ->fetchAssociative();
        if (false === $row) {
            return null;
        }
        $refundedOn = null === $row['refunded_on'] ? null : CalendarDate::parse((string) $row['refunded_on']);
        $reversedOn = null === $row['reversed_on'] ? null : CalendarDate::parse((string) $row['reversed_on']);

        return new AcknowledgementView(
            (string) $row['id'],
            (string) $row['gift_id'],
            \sprintf('G-%08d', (int) $row['number']),
            (string) $row['donor_name'],
            CalendarDate::parse((string) $row['issued_on']),
            CalendarDate::parse((string) $row['received_on']),
            (int) $row['amount_minor'],
            (string) $row['currency'],
            (string) $row['method'],
            null === $row['external_reference'] ? null : (string) $row['external_reference'],
            null !== $reversedOn || null !== $refundedOn,
            $refundedOn ?? $reversedOn,
            null !== $refundedOn ? self::REFUND_REASON : (null === $row['reversal_reason'] ? null : (string) $row['reversal_reason']),
            $this->lines((string) $row['id']),
        );
    }

    /** @return list<AcknowledgementLine> */
    private function lines(string $id): array
    {
        return array_values(array_map(
            static fn (array $line): AcknowledgementLine => new AcknowledgementLine((string) $line['fund_id'], (string) $line['fund_name'], (int) $line['amount_minor']),
            $this->connection->createQueryBuilder()
                ->select('fund_id', 'fund_name', 'amount_minor')
                ->from('giving_acknowledgement_lines')
                ->where('acknowledgement_id = :id')
                ->setParameter('id', $id)
                ->orderBy('fund_name')
                ->addOrderBy('fund_id')
                ->fetchAllAssociative(),
        ));
    }
}
