<?php

declare(strict_types=1);

namespace App\Giving\Infrastructure\Persistence;

use App\Giving\Domain\GivingException;
use App\Giving\Domain\Reconciliation;
use App\Giving\Domain\ReconciliationReview;
use App\Giving\Domain\Repository\ReconciliationRepository;
use App\Shared\Domain\CalendarDate;
use App\Shared\Infrastructure\Persistence\Timestamps;
use Closure;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\DriverException;
use Doctrine\DBAL\ParameterType;

/**
 * Reconciliation batches, items and reviews. Every integrity failure (SQLSTATE class 23: the unique reference and gift
 * membership, the snapshot trigger, a duplicate review version) becomes Java's evidence conflict.
 */
final readonly class DbalReconciliationStore implements ReconciliationRepository
{
    /** Per gift: the whole amount when reversed, otherwise its provider refunds. */
    private const string REVERSED = 'CASE WHEN EXISTS (SELECT 1 FROM giving_reversals r WHERE r.gift_id = g.id) THEN g.amount_minor'
        .' ELSE (SELECT COALESCE(SUM(f.amount_minor), 0) FROM giving_payment_refunds f WHERE f.gift_id = g.id) END';

    public function __construct(private Connection $connection)
    {
    }

    public function createdBy(string $requestId, string $hash): ?string
    {
        return $this->request('giving_reconciliation_batches', $requestId, $hash);
    }

    public function reviewedBy(string $requestId, string $hash): ?string
    {
        return $this->request('giving_reconciliation_reviews', $requestId, $hash);
    }

    public function insert(Reconciliation $batch, string $hash): void
    {
        $this->translated(function () use ($batch, $hash): void {
            $this->connection->insert('giving_reconciliation_batches', [
                'id' => $batch->id,
                'reference' => $batch->reference,
                'expected_minor' => $batch->expectedMinor,
                'actual_minor' => $batch->actualMinor,
                'currency' => $batch->currency,
                'reconciled_on' => $batch->reconciledOn->iso(),
                'reason' => $batch->reason,
                'created_by' => $batch->createdBy,
                'created_at' => Timestamps::toDatabase($batch->createdAt),
                'request_hash' => $hash,
            ], ['expected_minor' => ParameterType::INTEGER, 'actual_minor' => ParameterType::INTEGER]);
            foreach ($batch->giftIds as $giftId) {
                $this->connection->insert('giving_reconciliation_items', ['batch_id' => $batch->id, 'gift_id' => $giftId]);
            }
            // As for gifts: run the deferred snapshot triggers now so a mismatch is translated and rolls back with the
            // command, then restore deferral for any later batch in the same transaction.
            $this->connection->executeStatement('SET CONSTRAINTS giving_batch_balance, giving_batch_item_balance IMMEDIATE');
            $this->connection->executeStatement('SET CONSTRAINTS giving_batch_balance, giving_batch_item_balance DEFERRED');
        });
    }

    public function find(string $id): ?Reconciliation
    {
        $row = $this->connection->createQueryBuilder()
            ->select('id', 'reference', 'expected_minor', 'actual_minor', 'currency', 'reconciled_on', 'reason', 'created_by', 'created_at')
            ->from('giving_reconciliation_batches')
            ->where('id = :id')
            ->setParameter('id', $id)
            ->fetchAssociative();
        if (false === $row) {
            return null;
        }
        $gifts = $this->connection->createQueryBuilder()
            ->select('gift_id')
            ->from('giving_reconciliation_items')
            ->where('batch_id = :id')
            ->setParameter('id', $id)
            ->orderBy('gift_id')
            ->fetchFirstColumn();

        return Reconciliation::reconstitute(
            (string) $row['id'],
            (string) $row['reference'],
            (int) $row['expected_minor'],
            (int) $row['actual_minor'],
            (string) $row['currency'],
            CalendarDate::parse((string) $row['reconciled_on']),
            (string) $row['reason'],
            (string) $row['created_by'],
            Timestamps::fromDatabase((string) $row['created_at']),
            array_values(array_map(strval(...), $gifts)),
        );
    }

    public function lock(string $id): void
    {
        $locked = $this->connection->createQueryBuilder()
            ->select('id')
            ->from('giving_reconciliation_batches')
            ->where('id = :id')
            ->setParameter('id', $id)
            ->forUpdate()
            ->fetchOne();
        if (false === $locked) {
            throw GivingException::missing();
        }
    }

    public function version(string $batchId): int
    {
        return (int) $this->connection->createQueryBuilder()
            ->select('COALESCE(MAX(version), 0)')
            ->from('giving_reconciliation_reviews')
            ->where('batch_id = :batch')
            ->setParameter('batch', $batchId)
            ->fetchOne();
    }

    public function reversedMinor(string $batchId): int
    {
        return (int) $this->connection->createQueryBuilder()
            ->select('COALESCE(SUM('.self::REVERSED.'), 0)')
            ->from('giving_reconciliation_items', 'i')
            ->innerJoin('i', 'giving_gifts', 'g', 'g.id = i.gift_id')
            ->where('i.batch_id = :batch')
            ->setParameter('batch', $batchId)
            ->fetchOne();
    }

    public function insertReview(ReconciliationReview $review, string $hash): void
    {
        $this->translated(function () use ($review, $hash): void {
            $this->connection->insert('giving_reconciliation_reviews', [
                'id' => $review->id,
                'batch_id' => $review->batchId,
                'version' => $review->version,
                'outcome' => $review->outcome,
                'note' => $review->note,
                'reversed_minor' => $review->reversedMinor,
                'created_by' => $review->createdBy,
                'created_at' => Timestamps::toDatabase($review->createdAt),
                'request_hash' => $hash,
            ], ['version' => ParameterType::INTEGER, 'reversed_minor' => ParameterType::INTEGER]);
        });
    }

    public function reconciled(string $giftId): bool
    {
        return false !== $this->connection->createQueryBuilder()
            ->select('1')
            ->from('giving_reconciliation_items')
            ->where('gift_id = :gift')
            ->setParameter('gift', $giftId)
            ->fetchOne();
    }

    private function request(string $table, string $id, string $hash): ?string
    {
        $saved = $this->connection->createQueryBuilder()
            ->select('request_hash')
            ->from($table)
            ->where('id = :id')
            ->setParameter('id', $id)
            ->fetchOne();
        if (false === $saved) {
            return null;
        }
        if ($saved !== $hash) {
            throw GivingException::conflict('This request identifier was used for different details.');
        }

        return $id;
    }

    private function translated(Closure $write): void
    {
        try {
            $write();
        } catch (DriverException $error) {
            if (str_starts_with((string) $error->getSQLState(), '23')) {
                throw GivingException::evidenceConflict();
            }
            throw $error;
        }
    }
}
