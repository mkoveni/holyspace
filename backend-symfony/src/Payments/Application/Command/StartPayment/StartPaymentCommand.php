<?php

declare(strict_types=1);

namespace App\Payments\Application\Command\StartPayment;

use App\Shared\Application\Command;

/** @implements Command<string> */
final readonly class StartPaymentCommand implements Command
{
    public function __construct(
        public string $actorId,
        public string $requestId,
        public string $donorId,
        public string $fundId,
        public int $amountMinor,
        public string $provider,
        public bool $recurring,
        public bool $selfService = false,
    ) {
    }
}
