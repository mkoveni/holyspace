<?php

declare(strict_types=1);

namespace App\Payments\Application\Command\StartGuestPayment;

use App\Shared\Application\Command;

/** @implements Command<string> */
final readonly class StartGuestPaymentCommand implements Command
{
    public function __construct(
        public string $requestId,
        public string $fundId,
        public int $amountMinor,
        public string $provider,
        public ?string $name,
        public string $email,
    ) {
    }
}
