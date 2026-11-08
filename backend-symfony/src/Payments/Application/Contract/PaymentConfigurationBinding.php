<?php

declare(strict_types=1);

namespace App\Payments\Application\Contract;

interface PaymentConfigurationBinding
{
    /**
     * Called in the order transaction: locks the provider head, rechecks the checkout controls and returns the immutable
     * revision a new order pins (null while the provider still uses deployment credentials).
     */
    public function bind(string $provider, string $mode, bool $recurring): ?string;
}
