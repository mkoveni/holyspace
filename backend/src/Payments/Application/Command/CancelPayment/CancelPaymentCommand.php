<?php

declare(strict_types=1);

namespace App\Payments\Application\Command\CancelPayment;

use App\Shared\Application\CQRS\Command;

final readonly class CancelPaymentCommand implements Command
{
    public function __construct(
        public string $personId,
        public string $id,
        public int $version,
    ) {
    }
}
