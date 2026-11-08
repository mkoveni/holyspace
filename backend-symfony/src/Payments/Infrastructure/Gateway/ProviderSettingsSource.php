<?php

declare(strict_types=1);

namespace App\Payments\Infrastructure\Gateway;

use App\Payments\Domain\PaymentOrder;

/** Which connection values a provider call uses: the current head, an order's pinned revision, or a callback's order. */
interface ProviderSettingsSource
{
    public function current(string $provider): ResolvedSettings;

    public function forOrder(PaymentOrder $order): GatewaySettings;

    /** Untrusted hints in the raw body select a candidate only; the gateway authenticates the body with it. */
    public function forNotification(string $provider, string $body): GatewaySettings;
}
