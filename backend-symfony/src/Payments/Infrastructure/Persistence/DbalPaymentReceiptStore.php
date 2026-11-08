<?php

declare(strict_types=1);

namespace App\Payments\Infrastructure\Persistence;

use App\Payments\Application\Contract\PaymentReceiptReader;
use App\Payments\Domain\PaymentReceipt;
use App\Payments\Domain\Repository\PaymentReceiptRepository;
use App\Shared\Infrastructure\Persistence\Timestamps;
use Doctrine\DBAL\Connection;

final readonly class DbalPaymentReceiptStore implements PaymentReceiptReader, PaymentReceiptRepository
{
    private const string COLUMNS = 'id, order_id, charge_id, donor_id, contact_id, gift_id, delivery_id, approved_by, review_hash, request_hash, evidence, created_at';

    private PaymentWrites $writes;

    public function __construct(private Connection $connection)
    {
        $this->writes = new PaymentWrites($connection);
    }

    public function receipt(string $id): ?PaymentReceipt
    {
        return $this->one('id = ?', $id);
    }

    public function forCharge(string $chargeId): ?PaymentReceipt
    {
        return $this->one('charge_id = ?', $chargeId);
    }

    public function insert(PaymentReceipt $r): void
    {
        $this->writes->execute(
            'INSERT INTO payments_receipts ('.self::COLUMNS.') VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$r->id, $r->orderId, $r->chargeId, $r->donorId, $r->contactId, $r->giftId, $r->deliveryId, $r->approvedBy, $r->reviewHash,
                $r->requestHash, $r->evidence, Timestamps::toDatabase($r->createdAt)],
        );
    }

    private function one(string $condition, string $value): ?PaymentReceipt
    {
        $r = $this->connection->fetchAssociative('SELECT '.self::COLUMNS.' FROM payments_receipts WHERE '.$condition, [$value]);

        return false === $r ? null : new PaymentReceipt(
            (string) $r['id'],
            (string) $r['order_id'],
            (string) $r['charge_id'],
            (string) $r['donor_id'],
            (string) $r['contact_id'],
            (string) $r['gift_id'],
            (string) $r['delivery_id'],
            (string) $r['approved_by'],
            (string) $r['review_hash'],
            (string) $r['request_hash'],
            (string) $r['evidence'],
            Timestamps::fromDatabase((string) $r['created_at'])
        );
    }
}
