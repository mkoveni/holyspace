<?php

declare(strict_types=1);

namespace App\Payments\Application\Command\RecordConfirmedRefund;

use App\Shared\Application\CQRS\Command;

final readonly class RecordConfirmedRefundCommand implements Command
{
    public function __construct(
        public string $chargeId,
        public int $amount,
        public string $reference,
        public int $version,
        public bool $confirmed,
    ) {
    }
}
