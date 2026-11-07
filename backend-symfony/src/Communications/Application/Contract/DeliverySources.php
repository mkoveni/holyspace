<?php

declare(strict_types=1);

namespace App\Communications\Application\Contract;

/** Remembers which originating context owns each delivery. */
interface DeliverySources
{
    public function context(string $deliveryId): ?string;

    public function associate(string $deliveryId, string $context): void;
}
