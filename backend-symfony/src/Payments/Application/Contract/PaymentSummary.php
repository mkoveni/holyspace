<?php

declare(strict_types=1);

namespace App\Payments\Application\Contract;

use DateTimeImmutable;

/** One order as staff see it; givingStatus is SIMULATION, AWAITING_PAYMENT, POSTING_PENDING or POSTED. */
final readonly class PaymentSummary
{
    public function __construct(
        public string $id,
        public string $donorId,
        public string $donorName,
        public string $fundId,
        public int $amountMinor,
        public string $provider,
        public string $mode,
        public bool $recurring,
        public string $status,
        public string $subscriptionStatus,
        public ?string $reviewReason,
        public int $version,
        public DateTimeImmutable $createdAt,
        public string $givingStatus,
    ) {
    }
}
