<?php

declare(strict_types=1);

namespace App\Payments\Application\Query\GetPaymentReceipt;

use App\Communications\Application\Contract\DeliveryReader;
use App\Payments\Application\Contract\PaymentReceiptReader;
use App\Payments\Application\PaymentAccess;
use App\Payments\Application\PaymentReceiptPreview;
use App\Payments\Application\ReceiptPreview;
use App\Payments\Domain\PaymentException;

final readonly class GetPaymentReceiptHandler
{
    public function __construct(
        private PaymentAccess $access,
        private PaymentReceiptPreview $previews,
        private PaymentReceiptReader $receipts,
        private DeliveryReader $deliveries,
    ) {
    }

    public function handle(GetPaymentReceiptQuery $q): ReceiptReview
    {
        $this->access->receiptsRead();
        $existing = $this->receipts->forCharge($q->chargeId);
        if (null !== $existing) {
            if ($existing->orderId !== $q->orderId) {
                throw PaymentException::missing();
            }
            $snapshot = $this->deliveries->delivery($existing->deliveryId);
            if (null === $snapshot) {
                return new ReceiptReview([], null, $existing->id, null);
            }

            return new ReceiptReview(
                [],
                new ReceiptPreview($snapshot->reviewHash, $snapshot->recipient, $snapshot->subject, $snapshot->body, (int) $snapshot->templateVersion),
                $existing->id,
                new ReceiptDelivery($snapshot->id, $snapshot->status, $snapshot->captured, $snapshot->version, $snapshot->errorCode),
            );
        }
        $detail = $this->previews->eligible($q->orderId, $q->chargeId);

        return new ReceiptReview(
            $this->previews->contacts($detail),
            null === $q->contactId ? null : $this->previews->preview($detail, $q->chargeId, $q->contactId),
            null,
            null,
        );
    }
}
