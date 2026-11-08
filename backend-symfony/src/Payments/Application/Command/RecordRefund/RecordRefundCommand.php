<?php

declare(strict_types=1);

namespace App\Payments\Application\Command\RecordRefund;

use App\Shared\Domain\CalendarDate;

final readonly class RecordRefundCommand
{
    public function __construct(
        public string $actorId,
        public string $orderId,
        public string $chargeId,
        public string $providerReference,
        public int $amountMinor,
        public CalendarDate $refundedOn,
        public string $reason,
    ) {
    }
}
