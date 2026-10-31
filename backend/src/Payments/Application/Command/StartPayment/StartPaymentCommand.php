<?php

declare(strict_types=1);

namespace App\Payments\Application\Command\StartPayment;

use App\Shared\Application\CQRS\Command;

final readonly class StartPaymentCommand implements Command
{
    public function __construct(
        public string $personId,
        public string $provider,
        public string $purpose,
        public string $targetId,
        public ?int $amountMinor,
        public bool $recurring,
        public string $requestKey,
    ) {
    }
}
