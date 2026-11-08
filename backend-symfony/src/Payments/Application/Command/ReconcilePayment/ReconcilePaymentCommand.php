<?php

declare(strict_types=1);

namespace App\Payments\Application\Command\ReconcilePayment;

final readonly class ReconcilePaymentCommand
{
    public function __construct(
        public string $actorId,
        public string $requestId,
        public string $orderId,
        public string $chargeId,
        public int $expectedVersion,
        public string $settlementReference,
        public int $feeMinor,
        public int $bankMinor,
        public string $note,
    ) {
    }
}
