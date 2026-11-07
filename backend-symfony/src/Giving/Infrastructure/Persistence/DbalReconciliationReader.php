<?php

declare(strict_types=1);

namespace App\Giving\Infrastructure\Persistence;

use App\Giving\Application\Contract\ReconciliationBatchView;
use App\Giving\Application\Contract\ReconciliationDetail;
use App\Giving\Application\Contract\ReconciliationItem;
use App\Giving\Application\Contract\ReconciliationPage;
use App\Giving\Application\Contract\ReconciliationReader;
use App\Giving\Application\Contract\ReconciliationReviewView;
use App\Giving\Application\Contract\ReconciliationSummary;
use App\People\Application\Contract\PersonDirectory;
use App\Shared\Domain\CalendarDate;
use App\Shared\Infrastructure\Persistence\Timestamps;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;

/** Reconciliation batches as read models. Donor names come from the People directory, never from People tables. */
final readonly class DbalReconciliationReader implements ReconciliationReader
{
    private const array BATCH_COLUMNS = ['id', 'reference', 'expected_minor', 'actual_minor', 'currency', 'reconciled_on', 'reason', 'created_by', 'created_at'];
    private const array REVIEW_COLUMNS = ['v.id', 'v.batch_id', 'v.version', 'v.outcome', 'v.note', 'v.reversed_minor', 'v.created_by', 'v.created_at'];
    private const int REVIEW_LIMIT = 100;

    public function __construct(private Connection $connection, private PersonDirectory $people)
    {
    }

    public function list(int $limit, int $offset): ReconciliationPage
    {
        $rows = $this->connection->createQueryBuilder()
            ->select(...self::BATCH_COLUMNS)
            ->from('giving_reconciliation_batches')
            ->orderBy('reconciled_on', 'DESC')
            ->addOrderBy('id')
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->fetchAllAssociative();
        $ids = array_values(array_map(static fn (array $row): string => (string) $row['id'], $rows));
        $items = $this->items($ids);
        $reviews = $this->latestReviews($ids);
        $total = $this->connection->createQueryBuilder()->select('COUNT(*)')->from('giving_reconciliation_batches')->fetchOne();

        return new ReconciliationPage(array_values(array_map(
            fn (array $row): ReconciliationSummary => $this->summary($row, $items[(string) $row['id']] ?? [], $reviews[(string) $row['id']] ?? null),
            $rows,
        )), (int) $total);
    }

    public function detail(string $id): ?ReconciliationDetail
    {
        $row = $this->connection->createQueryBuilder()
            ->select(...self::BATCH_COLUMNS)
            ->from('giving_reconciliation_batches')
            ->where('id = :id')
            ->setParameter('id', $id)
            ->fetchAssociative();
        if (false === $row) {
            return null;
        }
        $items = $this->items([$id])[$id] ?? [];
        $reviews = array_values(array_map(
            self::review(...),
            $this->connection->createQueryBuilder()
                ->select(...self::REVIEW_COLUMNS)
                ->from('giving_reconciliation_reviews', 'v')
                ->where('v.batch_id = :id')
                ->setParameter('id', $id)
                ->orderBy('v.version', 'DESC')
                ->setMaxResults(self::REVIEW_LIMIT)
                ->fetchAllAssociative(),
        ));

        return new ReconciliationDetail($this->summary($row, $items, $reviews[0] ?? null), $items, $reviews);
    }

    /**
     * @param array<string, mixed>     $row
     * @param list<ReconciliationItem> $items
     */
    private function summary(array $row, array $items, ?ReconciliationReviewView $review): ReconciliationSummary
    {
        $batch = new ReconciliationBatchView(
            (string) $row['id'],
            (string) $row['reference'],
            (int) $row['expected_minor'],
            (int) $row['actual_minor'],
            (string) $row['currency'],
            CalendarDate::parse((string) $row['reconciled_on']),
            (string) $row['reason'],
            (string) $row['created_by'],
            Timestamps::fromDatabase((string) $row['created_at']),
            array_map(static fn (ReconciliationItem $item): string => $item->giftId, $items),
        );

        return ReconciliationSummary::of($batch, $items, $review);
    }

    /**
     * Items per batch, ordered by received date then gift id.
     *
     * @param list<string> $batchIds
     *
     * @return array<string, list<ReconciliationItem>>
     */
    private function items(array $batchIds): array
    {
        if ([] === $batchIds) {
            return [];
        }
        $rows = $this->connection->createQueryBuilder()
            ->select(
                'i.batch_id',
                'g.id',
                'g.donor_id',
                'g.received_on',
                'g.amount_minor',
                'EXISTS (SELECT 1 FROM giving_reversals r WHERE r.gift_id = g.id) AS reversed',
                '(SELECT COALESCE(SUM(f.amount_minor), 0) FROM giving_payment_refunds f WHERE f.gift_id = g.id) AS refunded',
            )
            ->from('giving_reconciliation_items', 'i')
            ->innerJoin('i', 'giving_gifts', 'g', 'g.id = i.gift_id')
            ->where('i.batch_id IN (:batches)')
            ->setParameter('batches', $batchIds, ArrayParameterType::STRING)
            ->orderBy('g.received_on')
            ->addOrderBy('g.id')
            ->fetchAllAssociative();
        $donors = array_values(array_unique(array_filter(array_map(static fn (array $row): ?string => null === $row['donor_id'] ? null : (string) $row['donor_id'], $rows))));
        $names = [];
        foreach ([] === $donors ? [] : $this->people->getAll($donors) as $entry) {
            $names[$entry->id] = $entry->name;
        }
        $items = [];
        foreach ($rows as $row) {
            $donor = null === $row['donor_id'] ? null : (string) $row['donor_id'];
            $items[(string) $row['batch_id']][] = new ReconciliationItem(
                (string) $row['id'],
                null === $donor ? 'Anonymous' : ($names[$donor] ?? 'Person unavailable'),
                CalendarDate::parse((string) $row['received_on']),
                (int) $row['amount_minor'],
                (bool) $row['reversed'],
                (int) $row['refunded'],
            );
        }

        return $items;
    }

    /**
     * @param list<string> $batchIds
     *
     * @return array<string, ReconciliationReviewView>
     */
    private function latestReviews(array $batchIds): array
    {
        if ([] === $batchIds) {
            return [];
        }
        $latest = [];
        foreach ($this->connection->createQueryBuilder()
            ->select(...self::REVIEW_COLUMNS)
            ->from('giving_reconciliation_reviews', 'v')
            ->where('v.batch_id IN (:batches)')
            ->andWhere('NOT EXISTS (SELECT 1 FROM giving_reconciliation_reviews n WHERE n.batch_id = v.batch_id AND n.version > v.version)')
            ->setParameter('batches', $batchIds, ArrayParameterType::STRING)
            ->fetchAllAssociative() as $row) {
            $latest[(string) $row['batch_id']] = self::review($row);
        }

        return $latest;
    }

    /** @param array<string, mixed> $row */
    private static function review(array $row): ReconciliationReviewView
    {
        return new ReconciliationReviewView(
            (string) $row['id'],
            (string) $row['batch_id'],
            (int) $row['version'],
            (string) $row['outcome'],
            (string) $row['note'],
            (int) $row['reversed_minor'],
            (string) $row['created_by'],
            Timestamps::fromDatabase((string) $row['created_at']),
        );
    }
}
