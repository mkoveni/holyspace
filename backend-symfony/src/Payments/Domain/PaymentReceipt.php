<?php

declare(strict_types=1);

namespace App\Payments\Domain;

use DateTimeImmutable;

/** Immutable source evidence for one approved donor confirmation of a charge. */
final readonly class PaymentReceipt
{
    public function __construct(
        public string $id,
        public string $orderId,
        public string $chargeId,
        public string $donorId,
        public string $contactId,
        public string $giftId,
        public string $deliveryId,
        public string $approvedBy,
        public string $reviewHash,
        public string $requestHash,
        public string $evidence,
        public DateTimeImmutable $createdAt,
    ) {
    }

    /** @return array{id: string} */
    public function __debugInfo(): array
    {
        return ['id' => $this->id];
    }
}
