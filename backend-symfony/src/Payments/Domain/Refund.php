<?php

declare(strict_types=1);

namespace App\Payments\Domain;

use App\Shared\Domain\CalendarDate;
use DateTimeImmutable;

/** Completed provider refund evidence; matched to a charge once and applied to Giving once (`payments_guard_refund`). */
final readonly class Refund
{
    public function __construct(
        public string $id,
        public string $orderId,
        public ?string $chargeId,
        public string $provider,
        public string $mode,
        public string $reference,
        public int $amountMinor,
        public CalendarDate $refundedOn,
        public bool $applied,
        public string $reason,
        public ?string $createdBy,
        public DateTimeImmutable $createdAt,
    ) {
    }
}
