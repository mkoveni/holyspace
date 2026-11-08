<?php

declare(strict_types=1);

namespace App\Payments\Infrastructure\Persistence;

use App\Communications\Application\Contract\DeliveryReader;
use App\Payments\Application\Contract\ChargeView;
use App\Payments\Application\Contract\DonorCharge;
use App\Payments\Application\Contract\DonorConfirmation;
use App\Payments\Application\Contract\DonorPayment;
use App\Payments\Application\Contract\DonorPaymentDetail;
use App\Payments\Application\Contract\DonorPaymentPage;
use App\Payments\Application\Contract\DonorPaymentReader;
use App\Payments\Application\Contract\DonorRefund;
use App\Payments\Application\Contract\PaymentReader;
use App\Payments\Application\Contract\PaymentReceiptReader;
use App\Payments\Domain\Refund;
use App\Shared\Infrastructure\Persistence\Timestamps;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;

/** Donor projections: only personal progress, refunds and the frozen confirmation (no staff or bank evidence). */
final readonly class DbalDonorPaymentStore implements DonorPaymentReader
{
    private const string COLUMNS = 'id, fund_id, amount_minor, provider, mode, recurring, status, subscription_status, version, created_at';

    public function __construct(
        private Connection $connection,
        private PaymentReader $payments,
        private PaymentReceiptReader $receipts,
        private DeliveryReader $deliveries,
    ) {
    }

    public function forDonor(string $donorId, int $limit, int $offset): DonorPaymentPage
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT '.self::COLUMNS.' FROM payments_orders WHERE donor_id = ? ORDER BY created_at DESC, id LIMIT ? OFFSET ?',
            [$donorId, $limit, $offset],
            [1 => ParameterType::INTEGER, 2 => ParameterType::INTEGER],
        );

        return new DonorPaymentPage(
            array_map(self::map(...), $rows),
            (int) $this->connection->fetchOne('SELECT count(*) FROM payments_orders WHERE donor_id = ?', [$donorId])
        );
    }

    public function ownDetail(string $donorId, string $orderId): ?DonorPaymentDetail
    {
        $row = $this->connection->fetchAssociative('SELECT '.self::COLUMNS.' FROM payments_orders WHERE id = ? AND donor_id = ?', [$orderId, $donorId]);
        $detail = false === $row ? null : $this->payments->detail($orderId);
        if (false === $row || null === $detail) {
            return null;
        }

        return new DonorPaymentDetail(
            self::map($row),
            array_map(function (ChargeView $c): DonorCharge {
                $receipt = $this->receipts->forCharge($c->charge->id);
                $delivery = null === $receipt ? null : $this->deliveries->delivery($receipt->deliveryId);

                return new DonorCharge(
                    $c->charge->id,
                    $c->charge->amountMinor,
                    $c->refundedMinor,
                    $c->charge->receivedOn,
                    $c->givingStatus,
                    $delivery->status ?? 'NOT_ISSUED',
                    $delivery->captured ?? false,
                    null === $delivery ? null : new DonorConfirmation($delivery->subject, $delivery->body)
                );
            }, $detail->charges),
            array_map(static fn (Refund $r): DonorRefund => new DonorRefund($r->amountMinor, $r->refundedOn, $r->applied), $detail->refunds),
        );
    }

    /** @param array<string, mixed> $r */
    private static function map(array $r): DonorPayment
    {
        return new DonorPayment(
            (string) $r['id'],
            (string) $r['fund_id'],
            (int) $r['amount_minor'],
            (string) $r['provider'],
            (string) $r['mode'],
            (bool) $r['recurring'],
            (string) $r['status'],
            (string) $r['subscription_status'],
            (int) $r['version'],
            Timestamps::fromDatabase((string) $r['created_at'])
        );
    }
}
