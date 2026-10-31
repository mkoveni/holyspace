<?php

declare(strict_types=1);

namespace App\Giving\Application\Command\RecordOnlineRefund;

use App\Shared\Application\CQRS\Command;

final readonly class RecordOnlineRefundCommand implements Command
{
    public function __construct(
        public string $giftId,
        public int $amount,
        public string $reference,
        public string $date,
    ) {
    }
}
