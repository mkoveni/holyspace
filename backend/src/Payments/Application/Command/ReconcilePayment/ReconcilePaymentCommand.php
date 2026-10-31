<?php

declare(strict_types=1);

namespace App\Payments\Application\Command\ReconcilePayment;

use App\Shared\Application\CQRS\Command;

final readonly class ReconcilePaymentCommand implements Command
{
    public function __construct(
        public string $id,
        public int $version,
        public string $note,
    ) {
    }
}
