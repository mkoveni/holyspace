<?php

declare(strict_types=1);

namespace App\Payments\Application\Command\RetryPaymentReceipt;

use App\Shared\Application\Command;

/** @implements Command<null> */
final readonly class RetryPaymentReceiptCommand implements Command
{
    public function __construct(public string $actorId, public string $orderId, public string $receiptId, public int $expectedVersion, public string $reason)
    {
    }
}
