<?php

declare(strict_types=1);

namespace App\Payments\Application\Port;

use App\Payments\Domain\Model\PaymentOrder;

interface PaymentGateway extends ProviderCatalogue
{
    public function providers(): array;

    public function mode(string $provider): string;

    /** Returns a fixed provider URL and optional POST form fields. No card data. */
    public function checkout(PaymentOrder $order, string $name, string $email): array;

    /** Verified, normalised event or null for unsupported notifications. */
    public function verify(string $provider, string $body, array $headers, string $clientIp): ?array;

    public function cancelSubscription(PaymentOrder $order, string $token): void;

    public function subscriptionStatus(PaymentOrder $order, string $token): string;
}
