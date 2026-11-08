<?php

declare(strict_types=1);

namespace App\Payments\Domain;

use DateTimeImmutable;

/** One immutable, versioned bank settlement review of a charge (Java `SettlementReview`). */
final readonly class SettlementReview
{
    public string $settlementReference;
    public string $note;

    public function __construct(
        public string $id,
        public string $orderId,
        public string $chargeId,
        public int $version,
        string $settlementReference,
        public int $feeMinor,
        public int $bankMinor,
        public int $refundedMinor,
        string $note,
        public string $createdBy,
        public DateTimeImmutable $createdAt,
        public string $requestHash,
    ) {
        $this->settlementReference = PaymentOrder::text($settlementReference, 200, 'Settlement reference');
        $this->note = PaymentOrder::text($note, 1000, 'Review note');
        if ($version < 1 || $feeMinor < 0 || $bankMinor < 0 || $feeMinor > 100_000_000 || $bankMinor > 100_000_000) {
            throw PaymentException::invalid('Enter valid fee and bank amounts.');
        }
    }

    /** Whether the bank amount still matches the charge less its applied refunds and this review's fee. */
    public function matches(Charge $charge, int $refunded): bool
    {
        return $this->refundedMinor === $refunded && $charge->amountMinor - $refunded - $this->feeMinor === $this->bankMinor;
    }
}
