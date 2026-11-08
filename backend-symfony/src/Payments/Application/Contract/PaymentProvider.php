<?php

declare(strict_types=1);

namespace App\Payments\Application\Contract;

/** A checkout option as staff and donors see it (Java `PaymentGateway.Provider`). */
final readonly class PaymentProvider
{
    public function __construct(
        public string $id,
        public string $name,
        public bool $available,
        public string $mode,
        public bool $recurring,
    ) {
    }
}
