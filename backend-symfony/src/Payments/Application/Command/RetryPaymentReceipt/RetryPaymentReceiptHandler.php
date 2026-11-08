<?php

declare(strict_types=1);

namespace App\Payments\Application\Command\RetryPaymentReceipt;

use App\Communications\Application\Contract\EmailDeliveries;
use App\Payments\Application\Contract\PaymentReceiptReader;
use App\Payments\Application\PaymentAccess;
use App\Payments\Application\PaymentReceiptPreview;
use App\Payments\Domain\PaymentException;
use App\Payments\Domain\Repository\PaymentRepository;
use App\Shared\Application\CommandHandler;

/** Retries a failed confirmation delivery while the charge is still eligible. Lock: the order. */
final readonly class RetryPaymentReceiptHandler implements CommandHandler
{
    public function __construct(
        private PaymentAccess $access,
        private PaymentReceiptReader $reader,
        private PaymentReceiptPreview $previews,
        private PaymentRepository $payments,
        private EmailDeliveries $deliveries,
    ) {
    }

    public function handle(RetryPaymentReceiptCommand $c): null
    {
        $this->access->receipts($c->actorId);
        $this->payments->lock($c->orderId) ?? throw PaymentException::missing();
        $this->access->receipts($c->actorId);
        $receipt = $this->reader->receipt($c->receiptId);
        if (null === $receipt || $receipt->orderId !== $c->orderId) {
            throw PaymentException::missing();
        }
        $this->previews->eligible($c->orderId, $receipt->chargeId);
        $this->deliveries->retry($c->actorId, $receipt->deliveryId, $c->expectedVersion, $c->reason);

        return null;
    }
}
