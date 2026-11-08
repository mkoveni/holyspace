<?php

declare(strict_types=1);

namespace App\Payments\Application\Command\StartDonorPayment;

use App\Shared\Application\Command;

/** @implements Command<string> */
final readonly class StartDonorPaymentCommand implements Command
{
    public function __construct(
        public string $actorId,
        public string $requestId,
        public string $fundId,
        public int $amountMinor,
        public string $provider,
        public bool $recurring,
    ) {
    }
}
