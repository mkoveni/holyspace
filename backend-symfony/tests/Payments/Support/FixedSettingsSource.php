<?php

declare(strict_types=1);

namespace App\Tests\Payments\Support;

use App\Payments\Domain\PaymentOrder;
use App\Payments\Infrastructure\Gateway\GatewaySettings;
use App\Payments\Infrastructure\Gateway\ProviderSettingsSource;
use App\Payments\Infrastructure\Gateway\ResolvedSettings;

/** One fixed set of connection values for every provider call, as Java's HostedPaymentGatewayTest builds the gateway. */
final readonly class FixedSettingsSource implements ProviderSettingsSource
{
    public function __construct(private GatewaySettings $settings)
    {
    }

    public function current(string $provider): ResolvedSettings
    {
        return new ResolvedSettings($this->settings, $this->settings->available($provider), 'payfast' === $provider);
    }

    public function forOrder(PaymentOrder $order): GatewaySettings
    {
        return $this->settings;
    }

    public function forNotification(string $provider, string $body): GatewaySettings
    {
        return $this->settings;
    }
}
