<?php

declare(strict_types=1);

namespace App\Payments\Application\Command\ApprovePaymentReceipt;

use App\Shared\Application\Command;

/** @implements Command<string> */
final readonly class ApprovePaymentReceiptCommand implements Command
{
    public function __construct(
        public string $actorId,
        public string $requestId,
        public string $orderId,
        public string $chargeId,
        public string $contactId,
        public string $reviewHash,
        public string $evidence,
    ) {
    }

    /** @return array{redacted: true} */
    public function __debugInfo(): array
    {
        return ['redacted' => true];
    }
}
