<?php

declare(strict_types=1);

namespace App\Payments\Domain;

use DateTimeImmutable;

/** Source evidence of the automatic confirmation of one verified public payment (one per charge). */
final readonly class GuestConfirmation
{
    public function __construct(
        public string $id,
        public string $orderId,
        public string $chargeId,
        public string $giftId,
        public string $deliveryId,
        public string $reviewHash,
        public DateTimeImmutable $createdAt,
    ) {
    }
}
